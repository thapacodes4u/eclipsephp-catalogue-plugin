<?php

namespace Eclipse\Catalogue\Filament\Forms\Components;

use Exception;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Storage;
use Str;

class ImageManager extends Field
{
    protected string $view = 'eclipse-catalogue::filament.forms.components.image-manager';

    protected string $collection = 'images';

    protected array $acceptedFileTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    protected array $temporaryImages = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->default([]);

        $this->afterStateHydrated(function (Field $component) {
            if ($component->getRecord()) {
                $component->refreshState();
            }
            $this->cleanupOldTempFiles();
        });

        $this->afterStateUpdated(function (Field $component) {
            if ($component->getRecord()) {
                $component->refreshState();
            }
        });

        $this->registerActions([
            $this->getUploadAction(),
            $this->getUrlUploadAction(),
            $this->getEditAction(),
            $this->getDeleteAction(),
            $this->getCoverAction(),
            $this->getReorderAction(),
        ]);

        $this->dehydrated(fn (?Model $record): bool => ! $record?->exists);

        $this->saveRelationshipsUsing(function (Field $component, ?Model $record) {
            if (! $record || ! $record->exists) {
                return;
            }

            $livewire = $component->getLivewire();
            if (method_exists($livewire, 'afterCreate') && property_exists($livewire, 'temporaryImages')) {
                return;
            }

            $state = $component->getState();

            if (! $state || ! is_array($state)) {
                return;
            }

            foreach ($state as $index => $item) {
                if (isset($item['id']) && $item['id']) {
                    $media = $record->getMedia($this->collection)->firstWhere('id', $item['id']);
                    if ($media) {
                        $media->setCustomProperty('name', $item['name'] ?? []);
                        $media->setCustomProperty('description', $item['description'] ?? []);
                        $media->setCustomProperty('is_cover', $item['is_cover'] ?? false);
                        $media->setCustomProperty('position', $index);
                        $media->save();
                    }
                } else {
                    if (isset($item['temp_file'])) {
                        $tempPath = storage_path('app/public/'.$item['temp_file']);
                        if (file_exists($tempPath)) {
                            $record->addMedia($tempPath)
                                ->usingFileName($item['file_name'] ?? basename($tempPath))
                                ->withCustomProperties([
                                    'name' => $item['name'] ?? [],
                                    'description' => $item['description'] ?? [],
                                    'is_cover' => $item['is_cover'] ?? false,
                                    'position' => $index,
                                ])
                                ->toMediaCollection($this->collection);

                            @unlink($tempPath);
                        }
                    } elseif (isset($item['temp_url'])) {
                        try {
                            $record->addMediaFromUrl($item['temp_url'])
                                ->usingFileName($item['file_name'] ?? basename($item['temp_url']))
                                ->withCustomProperties([
                                    'name' => $item['name'] ?? [],
                                    'description' => $item['description'] ?? [],
                                    'is_cover' => $item['is_cover'] ?? false,
                                    'position' => $index,
                                ])
                                ->toMediaCollection($this->collection);
                        } catch (Exception $e) {
                        }
                    }
                }
            }

            $existingIds = collect($state)->pluck('id')->filter()->toArray();
            $record->getMedia($this->collection)
                ->whereNotIn('id', $existingIds)
                ->each(fn ($media) => $media->delete());

            $this->ensureSingleCoverImage($record);
            $this->cleanupOldTempFiles();
        });

    }

    public function collection(string $collection): static
    {
        $this->collection = $collection;

        return $this;
    }

    public function acceptedFileTypes(array $types): static
    {
        $this->acceptedFileTypes = $types;

        return $this;
    }

    public function getAvailableLocales(): array
    {
        $locales = [];

        try {
            $livewire = $this->getLivewire();

            if ($livewire && method_exists($livewire, 'getTranslatableLocales')) {
                $plugin = filament('spatie-laravel-translatable');
                foreach ($livewire->getTranslatableLocales() as $locale) {
                    $locales[$locale] = $plugin->getLocaleLabel($locale) ?? $locale;
                }
            }
        } catch (Exception $e) {
        }

        if (empty($locales)) {
            $locales = config('eclipsephp.locales', ['en' => 'English']);
        }

        return $locales;
    }

    public function getSelectedLocale(): string
    {
        try {
            $livewire = $this->getLivewire();
            if ($livewire && property_exists($livewire, 'activeLocale')) {
                return $livewire->activeLocale;
            }
        } catch (Exception $e) {
        }

        return app()->getLocale();
    }

    public function getUploadAction(): Action
    {
        return Action::make('upload')
            ->label('Upload Files')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('primary')
            ->modalHeading('Upload Images')
            ->modalSubmitActionLabel('Upload')
            ->schema([
                FileUpload::make('files')
                    ->label('Choose files')
                    ->multiple()
                    ->image()
                    ->acceptedFileTypes($this->acceptedFileTypes)
                    ->imagePreviewHeight('200')
                    ->required()
                    ->directory('temp-images')
                    ->visibility('public')
                    ->storeFiles(true)
                    ->preserveFilenames(),
            ])
            ->action(function (array $data): void {
                if (! isset($data['files'])) {
                    return;
                }

                $record = $this->getRecord();

                // Handle create forms (no record yet)
                if (! $record) {
                    $currentState = $this->getState() ?: [];
                    $maxPosition = count($currentState) - 1;

                    // FileUpload returns array of file paths when storeFiles is true
                    foreach ($data['files'] as $filePath) {
                        if (is_string($filePath)) {
                            $fullPath = storage_path('app/public/'.$filePath);

                            if (file_exists($fullPath)) {
                                $tempId = 'temp_'.uniqid();
                                $fileName = basename($filePath);

                                $currentState[] = [
                                    'id' => null,
                                    'temp_id' => $tempId,
                                    'temp_file' => $filePath,
                                    'uuid' => (string) Str::uuid(),
                                    'url' => Storage::url($filePath),
                                    'thumb_url' => Storage::url($filePath),
                                    'preview_url' => Storage::url($filePath),
                                    'name' => [],
                                    'description' => [],
                                    'is_cover' => count($currentState) === 0,
                                    'position' => ++$maxPosition,
                                    'file_name' => $fileName,
                                    'mime_type' => mime_content_type($fullPath),
                                    'size' => filesize($fullPath),
                                ];
                            }
                        }
                    }

                    $this->state($currentState);

                    Notification::make()
                        ->title(count($data['files']).' image(s) added successfully')
                        ->success()
                        ->send();

                    return;
                }

                // Handle edit forms (record exists)
                $existingCount = $record->getMedia($this->collection)->count();
                $maxPosition = $record->getMedia($this->collection)->max(fn ($m) => $m->getCustomProperty('position', 0)) ?? -1;
                $uploadCount = 0;

                foreach ($data['files'] as $filePath) {
                    if (is_string($filePath)) {
                        $fullPath = storage_path('app/public/'.$filePath);

                        if (file_exists($fullPath)) {
                            $record->addMedia($fullPath)
                                ->usingFileName(basename($filePath))
                                ->withCustomProperties([
                                    'name' => [],
                                    'description' => [],
                                    'is_cover' => $existingCount === 0 && $uploadCount === 0,
                                    'position' => ++$maxPosition,
                                ])
                                ->toMediaCollection($this->collection);

                            $uploadCount++;

                            // Clean up the temp file after adding to media library
                            @unlink($fullPath);
                        }
                    }
                }

                $this->refreshState();

                Notification::make()
                    ->title($uploadCount.' image(s) uploaded successfully')
                    ->success()
                    ->send();
            })
            ->modalWidth('lg')
            ->closeModalByClickingAway(false);
    }

    public function getUrlUploadAction(): Action
    {
        return Action::make('urlUpload')
            ->label('Add from URL')
            ->icon('heroicon-o-link')
            ->color('gray')
            ->modalHeading('Add Images from URLs')
            ->modalSubmitActionLabel('Add Images')
            ->schema([
                Textarea::make('urls')
                    ->label('Image URLs')
                    ->placeholder("https://example.com/image1.jpg\nhttps://example.com/image2.jpg")
                    ->rows(5)
                    ->required()
                    ->helperText('Enter one URL per line'),
            ])
            ->action(function (array $data): void {
                if (! isset($data['urls'])) {
                    return;
                }

                $urls = array_filter(array_map('trim', explode("\n", $data['urls'])));
                $record = $this->getRecord();

                // Handle create forms
                if (! $record) {
                    $currentState = $this->getState() ?: [];
                    $maxPosition = count($currentState) - 1;
                    $successCount = 0;
                    $failedUrls = [];

                    foreach ($urls as $url) {
                        if (filter_var($url, FILTER_VALIDATE_URL)) {
                            // For create forms, we'll store the URL and download it after creation
                            $tempId = 'temp_'.uniqid();
                            $currentState[] = [
                                'id' => null,
                                'temp_id' => $tempId,
                                'temp_url' => $url,
                                'uuid' => (string) Str::uuid(),
                                'url' => $url,
                                'thumb_url' => $url,
                                'preview_url' => $url,
                                'name' => [],
                                'description' => [],
                                'is_cover' => count($currentState) === 0,
                                'position' => ++$maxPosition,
                                'file_name' => basename($url),
                                'mime_type' => 'image/*',
                                'size' => 0,
                            ];
                            $successCount++;
                        } else {
                            $failedUrls[] = $url;
                        }
                    }

                    $this->state($currentState);

                    if ($successCount > 0) {
                        Notification::make()
                            ->title($successCount.' image(s) added successfully')
                            ->success()
                            ->send();
                    }

                    if (! empty($failedUrls)) {
                        Notification::make()
                            ->title('Some URLs failed')
                            ->body('Failed URLs: '.implode(', ', array_slice($failedUrls, 0, 3)).(count($failedUrls) > 3 ? ' and '.(count($failedUrls) - 3).' more' : ''))
                            ->warning()
                            ->send();
                    }

                    return;
                }

                // Handle edit forms
                $existingCount = $record->getMedia($this->collection)->count();
                $maxPosition = $record->getMedia($this->collection)->max(fn ($m) => $m->getCustomProperty('position', 0)) ?? -1;
                $successCount = 0;
                $failedUrls = [];

                foreach ($urls as $url) {
                    if (filter_var($url, FILTER_VALIDATE_URL)) {
                        try {
                            $record->addMediaFromUrl($url)
                                ->withCustomProperties([
                                    'name' => [],
                                    'description' => [],
                                    'is_cover' => $existingCount === 0 && $successCount === 0,
                                    'position' => ++$maxPosition,
                                ])
                                ->toMediaCollection($this->collection);

                            $successCount++;
                        } catch (Exception $e) {
                            $failedUrls[] = $url;
                        }
                    } else {
                        $failedUrls[] = $url;
                    }
                }

                $this->refreshState();

                if ($successCount > 0) {
                    Notification::make()
                        ->title("{$successCount} image(s) added successfully")
                        ->success()
                        ->send();
                }

                if (! empty($failedUrls)) {
                    Notification::make()
                        ->title('Some URLs failed')
                        ->body('Failed URLs: '.implode(', ', array_slice($failedUrls, 0, 3)).(count($failedUrls) > 3 ? ' and '.(count($failedUrls) - 3).' more' : ''))
                        ->warning()
                        ->send();
                }
            })
            ->modalWidth('lg')
            ->closeModalByClickingAway(false);
    }

    public function getReorderAction(): Action
    {
        return Action::make('reorder')
            ->action(function (array $arguments): void {
                if (! isset($arguments['items'])) {
                    return;
                }

                $newOrder = $arguments['items'];
                $record = $this->getRecord();

                // Handle create forms - reorder state
                if (! $record) {
                    $state = $this->getState();
                    $orderedState = [];

                    // Reorder based on the new UUID order
                    foreach ($newOrder as $position => $uuid) {
                        $item = collect($state)->firstWhere('uuid', $uuid);
                        if ($item) {
                            $item['position'] = $position;
                            $orderedState[] = $item;
                        }
                    }

                    $this->state($orderedState);

                    Notification::make()
                        ->title('Images reordered successfully')
                        ->success()
                        ->send();

                    return;
                }

                // Handle edit forms - update media positions
                $record->load('media');
                $mediaCollection = $record->getMedia($this->collection);

                foreach ($newOrder as $position => $uuid) {
                    $media = $mediaCollection->firstWhere('uuid', $uuid);
                    if ($media) {
                        $media->setCustomProperty('position', $position);
                        $media->save();
                    }
                }

                $record->load('media');

                $this->refreshState();

                Notification::make()
                    ->title('Images reordered successfully')
                    ->success()
                    ->send();
            })
            ->livewireClickHandlerEnabled(false);
    }

    public function getEditAction(): Action
    {
        return Action::make('editImage')
            ->label('Edit Image')
            ->modalHeading('Edit Image Details')
            ->modalSubmitActionLabel('Save Changes')
            ->schema(function (array $arguments) {
                $args = $arguments['arguments'] ?? $arguments;
                $uuid = $args['uuid'] ?? null;
                $selectedLocale = $args['selectedLocale'] ?? $this->getSelectedLocale();
                $state = $this->getState();
                $image = collect($state)->firstWhere('uuid', $uuid);

                if (! $image) {
                    return [];
                }

                $locales = $this->getAvailableLocales();

                $fields = [];

                $fields[] = Placeholder::make('preview')
                    ->label('')
                    ->content(function () use ($image) {
                        return view('eclipse-catalogue::filament.forms.components.image-preview-inline', [
                            'url' => $image['preview_url'] ?? $image['url'],
                            'filename' => $image['file_name'],
                        ]);
                    });

                if (count($locales) > 1) {
                    $fields[] = Select::make('edit_locale')
                        ->label('Language')
                        ->options($locales)
                        ->default($selectedLocale)
                        ->live()
                        ->afterStateUpdated(function ($state, $set) use ($image) {
                            $set('name', $image['name'][$state] ?? '');
                            $set('description', $image['description'][$state] ?? '');
                        });
                }

                $fields[] = TextInput::make('name')
                    ->label('Name')
                    ->default($image['name'][$selectedLocale] ?? '');

                $fields[] = Textarea::make('description')
                    ->label('Description')
                    ->rows(3)
                    ->default($image['description'][$selectedLocale] ?? '');

                return $fields;
            })
            ->action(function (array $data, array $arguments): void {
                $args = $arguments['arguments'] ?? $arguments;
                $uuid = $args['uuid'] ?? null;

                if (! $uuid) {
                    return;
                }

                $record = $this->getRecord();

                // Handle create forms - update state directly
                if (! $record) {
                    $state = $this->getState();
                    $imageIndex = collect($state)->search(fn ($item) => $item['uuid'] === $uuid);

                    if ($imageIndex !== false) {
                        $locale = $data['edit_locale'] ?? array_key_first($this->getAvailableLocales());
                        $state[$imageIndex]['name'][$locale] = $data['name'] ?? '';
                        $state[$imageIndex]['description'][$locale] = $data['description'] ?? '';

                        $this->state($state);

                        Notification::make()
                            ->title('Image details updated')
                            ->success()
                            ->send();
                    }

                    return;
                }

                // Handle edit forms - update media
                $media = $record->getMedia($this->collection)->firstWhere('uuid', $uuid);
                if ($media) {
                    $nameTranslations = $media->getCustomProperty('name', []);
                    $descriptionTranslations = $media->getCustomProperty('description', []);

                    $locale = $data['edit_locale'] ?? array_key_first($this->getAvailableLocales());
                    $nameTranslations[$locale] = $data['name'] ?? '';
                    $descriptionTranslations[$locale] = $data['description'] ?? '';

                    $media->setCustomProperty('name', $nameTranslations);
                    $media->setCustomProperty('description', $descriptionTranslations);
                    $media->save();

                    $this->refreshState();

                    Notification::make()
                        ->title('Image details updated')
                        ->success()
                        ->send();
                }
            })
            ->modalWidth('lg');
    }

    public function getCoverAction(): Action
    {
        return Action::make('setCover')
            ->label('Set as Cover')
            ->requiresConfirmation()
            ->modalHeading('Set as Cover Image')
            ->modalDescription('This image will be used as the main product image.')
            ->modalSubmitActionLabel('Set as Cover')
            ->action(function (array $arguments): void {
                $args = $arguments['arguments'] ?? $arguments;
                $uuid = $args['uuid'] ?? null;

                if (! $uuid) {
                    return;
                }

                $record = $this->getRecord();

                // Handle create forms - update state
                if (! $record) {
                    $state = $this->getState();

                    // Remove is_cover from all images
                    $newState = collect($state)->map(function ($item) use ($uuid) {
                        $item['is_cover'] = $item['uuid'] === $uuid;

                        return $item;
                    })->toArray();

                    $this->state($newState);

                    Notification::make()
                        ->title('Cover image updated')
                        ->success()
                        ->send();

                    return;
                }

                // Handle edit forms - update media
                $record->getMedia($this->collection)->each(function ($media) {
                    $media->setCustomProperty('is_cover', false);
                    $media->save();
                });

                $targetMedia = $record->getMedia($this->collection)->firstWhere('uuid', $uuid);
                if ($targetMedia) {
                    $targetMedia->setCustomProperty('is_cover', true);
                    $targetMedia->save();
                }

                $this->refreshState();

                Notification::make()
                    ->title('Cover image updated')
                    ->success()
                    ->send();
            });
    }

    protected function mediaToArray(Media $media): array
    {
        return [
            'id' => $media->id,
            'uuid' => $media->uuid,
            'url' => $media->getUrl(),
            'thumb_url' => $media->getUrl('thumb'),
            'preview_url' => $media->getUrl('preview'),
            'name' => $media->getCustomProperty('name', []),
            'description' => $media->getCustomProperty('description', []),
            'is_cover' => $media->getCustomProperty('is_cover', false),
            'position' => $media->getCustomProperty('position', 0),
            'file_name' => $media->file_name,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
        ];
    }

    public function refreshState(): void
    {
        $record = $this->getRecord();
        if (! $record) {
            $this->state([]);

            return;
        }

        $record->load('media');

        $media = $record->getMedia($this->collection)
            ->map(fn (Media $media) => $this->mediaToArray($media))
            ->sortBy('position')
            ->values()
            ->toArray();

        $this->state($media);
    }

    protected function ensureSingleCoverImage(Model $record): void
    {
        $coverMedia = $record->getMedia($this->collection)
            ->filter(fn ($media) => $media->getCustomProperty('is_cover', false));

        if ($coverMedia->count() > 1) {
            $coverMedia->skip(1)->each(function ($media) {
                $media->setCustomProperty('is_cover', false);
                $media->save();
            });
        }

        if ($coverMedia->count() === 0 && $record->getMedia($this->collection)->count() > 0) {
            $firstMedia = $record->getMedia($this->collection)->first();
            $firstMedia->setCustomProperty('is_cover', true);
            $firstMedia->save();
        }
    }

    protected function cleanupOldTempFiles(): void
    {
        $tempDir = storage_path('app/public/temp-images');
        if (! file_exists($tempDir)) {
            return;
        }

        // Clean up files older than 24 hours
        $files = glob($tempDir.'/*');
        $now = time();

        foreach ($files as $file) {
            if (is_file($file) && $now - filemtime($file) >= 86400) { // 24 hours
                @unlink($file);
            }
        }
    }

    public function getDeleteAction(): Action
    {
        return Action::make('deleteImage')
            ->label('Delete')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Delete Image')
            ->modalDescription('Are you sure you want to delete this image? This action cannot be undone.')
            ->modalSubmitActionLabel('Delete')
            ->action(function (array $arguments): void {
                $args = $arguments['arguments'] ?? $arguments;
                $uuid = $args['uuid'] ?? null;

                if (! $uuid) {
                    return;
                }

                $record = $this->getRecord();

                // Handle create forms - remove from state
                if (! $record) {
                    $state = $this->getState();
                    $imageIndex = collect($state)->search(fn ($item) => $item['uuid'] === $uuid);

                    if ($imageIndex !== false) {
                        $wasCover = $state[$imageIndex]['is_cover'] ?? false;

                        // Clean up temp file if it exists
                        if (isset($state[$imageIndex]['temp_file'])) {
                            $tempPath = storage_path('app/public/'.$state[$imageIndex]['temp_file']);
                            if (file_exists($tempPath)) {
                                @unlink($tempPath);
                            }
                        }

                        // Remove from state
                        $newState = collect($state)->reject(fn ($item) => $item['uuid'] === $uuid)->values()->toArray();

                        // If deleted image was cover, make first image cover
                        if ($wasCover && count($newState) > 0) {
                            $newState[0]['is_cover'] = true;
                        }

                        $this->state($newState);

                        Notification::make()
                            ->title('Image removed')
                            ->success()
                            ->send();
                    }

                    return;
                }

                // Handle edit forms - delete media
                $media = $record->getMedia($this->collection)->firstWhere('uuid', $uuid);

                if (! $media) {
                    Notification::make()
                        ->title('Could not find image to delete')
                        ->warning()
                        ->send();

                    return;
                }

                $wasCover = $media->getCustomProperty('is_cover', false);

                $media->delete();

                $record->load('media');

                if ($wasCover) {
                    $remainingMedia = $record->getMedia($this->collection);
                    if ($remainingMedia->count() > 0) {
                        $firstMedia = $remainingMedia->first();
                        $firstMedia->setCustomProperty('is_cover', true);
                        $firstMedia->save();
                    }
                }

                $this->refreshState();

                Notification::make()
                    ->title('Image deleted')
                    ->success()
                    ->send();
            });
    }
}
