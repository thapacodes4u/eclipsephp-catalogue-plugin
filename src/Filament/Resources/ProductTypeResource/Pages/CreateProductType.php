<?php

namespace Eclipse\Catalogue\Filament\Resources\ProductTypeResource\Pages;

use Eclipse\Catalogue\Filament\Resources\ProductTypeResource;
use Eclipse\Catalogue\Models\ProductType;
use Eclipse\Catalogue\Traits\HandlesTenantData;
use Eclipse\Catalogue\Traits\HasProductTypeForm;
use Eclipse\Catalogue\Traits\HasTenantFields;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use LaraZeus\SpatieTranslatable\Resources\Pages\CreateRecord\Concerns\Translatable;

class CreateProductType extends CreateRecord
{
    use HandlesTenantData, HasProductTypeForm, HasTenantFields, Translatable;

    protected static string $resource = ProductTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
        ];
    }

    protected function getFormTenantFlags(): array
    {
        return ['is_active', 'is_default'];
    }

    protected function getFormMutuallyExclusiveFlagSets(): array
    {
        return [];
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components($this->buildProductTypeFormSchema());
    }

    protected function handleRecordCreation(array $data): Model
    {
        // Extract tenant data from form
        $tenantData = $this->extractTenantDataFromFormData($data);

        // Clean main record data
        $typeData = $this->cleanFormDataForMainRecord($data);

        // Use the model's createWithTenantData method
        return ProductType::createWithTenantData($typeData, $tenantData);
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction()
                ->action(function () {
                    // Store current tenant data before validation/saving
                    $this->storeCurrentTenantData();
                    $this->validateDefaultConstraintsBeforeSave();
                    $this->create();
                }),
            $this->getCancelFormAction(),
        ];
    }
}
