<?php

namespace Eclipse\Catalogue\Filament\Resources\ProductStatusResource\Pages;

use Eclipse\Catalogue\Filament\Resources\ProductStatusResource;
use Eclipse\Catalogue\Models\ProductStatus;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use LaraZeus\SpatieTranslatable\Resources\Pages\CreateRecord\Concerns\Translatable;

class CreateProductStatus extends CreateRecord
{
    use Translatable;

    protected static string $resource = ProductStatusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $currentTenant = Filament::getTenant();
        if ($currentTenant) {
            $data['site_id'] = $currentTenant->id;
        }

        if (($data['allow_price_display'] ?? true) === false) {
            $data['allow_sale'] = false;
        }

        if (! empty($data['is_default'])) {
            $tenantFK = config('eclipse-catalogue.tenancy.foreign_key');
            $query = ProductStatus::query()->where('is_default', true);
            if ($tenantFK && isset($data[$tenantFK])) {
                $query->where($tenantFK, $data[$tenantFK]);
            }
            $query->update(['is_default' => false]);
        }

        return $data;
    }
}
