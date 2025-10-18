<?php

namespace Eclipse\Catalogue\Filament\Resources\ProductResource\Pages;

use Eclipse\Catalogue\Filament\Resources\Concerns\HandlesImageUploads;
use Eclipse\Catalogue\Filament\Resources\ProductResource;
use Eclipse\Catalogue\Models\Group;
use Eclipse\Catalogue\Models\Product;
use Eclipse\Catalogue\Models\ProductStatus;
use Eclipse\Catalogue\Models\Property;
use Eclipse\Catalogue\Traits\HandlesTenantData;
use Eclipse\Catalogue\Traits\HasTenantFields;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use LaraZeus\SpatieTranslatable\Resources\Pages\CreateRecord\Concerns\Translatable;

class CreateProduct extends CreateRecord
{
    use HandlesImageUploads;
    use HandlesTenantData, HasTenantFields;
    use Translatable;

    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (isset($data['images'])) {
            $this->temporaryImages = $data['images'];
            unset($data['images']);
        }

        foreach (array_keys($data) as $key) {
            if (str_starts_with($key, 'property_values_') || str_starts_with($key, 'custom_property_')) {
                unset($data[$key]);
            }
        }

        return $data;
    }

    protected function getFormTenantFlags(): array
    {
        return ['is_active', 'has_free_delivery'];
    }

    protected function getFormMutuallyExclusiveFlagSets(): array
    {
        return [];
    }

    protected function handleRecordCreation(array $data): Model
    {
        $tenantData = $this->extractTenantDataFromFormData($data);
        // Auto-assign default product status if missing
        $tenantFK = config('eclipse-catalogue.tenancy.foreign_key');
        if (! $tenantFK) {
            if (empty($tenantData['product_status_id'] ?? null)) {
                $default = ProductStatus::query()->where('is_default', true)->orderBy('priority')->first();
                if ($default) {
                    $tenantData['product_status_id'] = $default->id;
                }
            }
        } else {
            foreach ($tenantData as $siteId => &$td) {
                if (empty($td['product_status_id'] ?? null)) {
                    $default = ProductStatus::query()->where($tenantFK, $siteId)->where('is_default', true)->orderBy('priority')->first();
                    if ($default) {
                        $td['product_status_id'] = $default->id;
                    }
                }
            }
            unset($td);
        }
        $productData = $this->cleanFormDataForMainRecord($data);

        return Product::createWithTenantData($productData, $tenantData);
    }

    protected function afterCreate(): void
    {
        /** @var Product $product */
        $product = $this->record;

        if (! $product) {
            return;
        }

        // Handle image uploads (from HandlesImageUploads trait logic)
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
                    } catch (\Exception $e) {
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

        $state = $this->form->getState();
        $rawState = $this->form->getRawState();
        $tenantData = $state['tenant_data'] ?? [];

        // Handle property values
        $propertyData = [];
        foreach ($rawState as $key => $value) {
            if (is_string($key) && str_starts_with($key, 'property_values_')) {
                $propertyId = str_replace('property_values_', '', $key);
                $propertyData[$propertyId] = $value;
            }
        }

        foreach ($propertyData as $propertyId => $values) {
            if ($values) {
                $valuesToAttach = is_array($values) ? $values : [$values];
                $valuesToAttach = array_filter($valuesToAttach);

                if (! empty($valuesToAttach)) {
                    $product->propertyValues()->attach($valuesToAttach);
                }
            }
        }

        // Handle custom properties
        $customPropertyData = [];
        foreach ($rawState as $key => $value) {
            if (is_string($key) && str_starts_with($key, 'custom_property_')) {
                $propertyId = str_replace('custom_property_', '', $key);
                $customPropertyData[$propertyId] = $value;
            }
        }

        foreach ($customPropertyData as $propertyId => $value) {
            $property = Property::find($propertyId);
            if ($property && $property->isCustomType()) {
                if ($property->supportsMultilang() && is_array($value)) {
                    $filteredValue = array_filter($value, fn ($v) => $v !== null && $v !== '');
                    if (! empty($filteredValue)) {
                        $product->setCustomPropertyValue($property, $value);
                    }
                } elseif ($value !== null && $value !== '') {
                    $product->setCustomPropertyValue($property, $value);
                }
            }
        }

        // Handle tenant/group associations
        $isTenancyEnabled = (bool) config('eclipse-catalogue.tenancy.model');
        if ($isTenancyEnabled) {
            foreach ($tenantData as $tenantId => $data) {
                $groupIds = array_filter(array_map('intval', (array) ($data['groups'] ?? [])));
                foreach ($groupIds as $groupId) {
                    $group = Group::find($groupId);
                    $tenantFK = config('eclipse-catalogue.tenancy.foreign_key', 'site_id');
                    if ($group && (int) $group->getAttribute($tenantFK) === (int) $tenantId) {
                        $group->addProduct($product);
                    }
                }
            }
        } else {
            $flatGroupIds = [];
            if (isset($state['groups'])) {
                $flatGroupIds = array_filter(array_map('intval', (array) $state['groups']));
            } else {
                foreach ($tenantData as $data) {
                    foreach ((array) ($data['groups'] ?? []) as $id) {
                        $flatGroupIds[] = (int) $id;
                    }
                }
                $flatGroupIds = array_values(array_unique(array_filter($flatGroupIds)));
            }

            foreach ($flatGroupIds as $groupId) {
                if ($group = Group::find($groupId)) {
                    $group->addProduct($product);
                }
            }
        }
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction()
                ->action(function () {
                    $this->storeCurrentTenantData();
                    $this->validateDefaultConstraintsBeforeSave();
                    $this->create();
                }),
            $this->getCancelFormAction(),
        ];
    }
}
