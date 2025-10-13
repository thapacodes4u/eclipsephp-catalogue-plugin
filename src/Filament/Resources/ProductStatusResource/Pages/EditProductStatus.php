<?php

namespace Eclipse\Catalogue\Filament\Resources\ProductStatusResource\Pages;

use Eclipse\Catalogue\Filament\Resources\ProductStatusResource;
use Eclipse\Catalogue\Models\ProductStatus;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use LaraZeus\SpatieTranslatable\Resources\Pages\EditRecord\Concerns\Translatable;

class EditProductStatus extends EditRecord
{
    use Translatable;

    protected static string $resource = ProductStatusResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // If price display is disabled, force allow_sale to false
        if (($data['allow_price_display'] ?? true) === false) {
            $data['allow_sale'] = false;
        }

        // Ensure only one default per tenant/site
        if (! empty($data['is_default'])) {
            $tenantFK = config('eclipse-catalogue.tenancy.foreign_key');
            $query = ProductStatus::query()->where('is_default', true)->where('id', '!=', $this->record->id);
            if ($tenantFK) {
                $query->where($tenantFK, $this->record->getAttribute($tenantFK));
            }
            $query->update(['is_default' => false]);
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
            DeleteAction::make(),
        ];
    }
}
