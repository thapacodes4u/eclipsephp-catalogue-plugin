<?php

namespace Eclipse\Catalogue\Filament\Resources\Concerns;

use Exception;

trait HandlesImageUploads
{
    public ?array $temporaryImages = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (isset($data['images'])) {
            $this->temporaryImages = $data['images'];
            unset($data['images']);
        }

        return $data;
    }

    protected function afterCreate(): void
    {

        $pendingImages = $this->temporaryImages;

        if (! empty($pendingImages) && is_array($pendingImages)) {
            foreach ($pendingImages as $index => $item) {
                if (isset($item['temp_file'])) {
                    $tempPath = storage_path('app/public/'.$item['temp_file']);

                    if (file_exists($tempPath)) {
                        $this->record->addMedia($tempPath)
                            ->usingFileName($item['file_name'] ?? basename($tempPath))
                            ->withCustomProperties([
                                'name' => $item['name'] ?? [],
                                'description' => $item['description'] ?? [],
                                'is_cover' => $item['is_cover'] ?? false,
                                'position' => $index,
                            ])
                            ->toMediaCollection('images');

                        @unlink($tempPath);
                    }
                } elseif (isset($item['temp_url'])) {
                    try {
                        $this->record->addMediaFromUrl($item['temp_url'])
                            ->usingFileName($item['file_name'] ?? basename($item['temp_url']))
                            ->withCustomProperties([
                                'name' => $item['name'] ?? [],
                                'description' => $item['description'] ?? [],
                                'is_cover' => $item['is_cover'] ?? false,
                                'position' => $index,
                            ])
                            ->toMediaCollection('images');
                    } catch (Exception $e) {
                    }
                }
            }

            $coverMedia = $this->record->getMedia('images')
                ->filter(fn ($media) => $media->getCustomProperty('is_cover', false));

            if ($coverMedia->count() > 1) {
                $coverMedia->skip(1)->each(function ($media) {
                    $media->setCustomProperty('is_cover', false);
                    $media->save();
                });
            }

            if ($coverMedia->count() === 0 && $this->record->getMedia('images')->count() > 0) {
                $firstMedia = $this->record->getMedia('images')->first();
                $firstMedia->setCustomProperty('is_cover', true);
                $firstMedia->save();
            }
        }
    }
}
