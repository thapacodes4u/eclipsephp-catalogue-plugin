<?php

namespace Eclipse\Catalogue\Filament\Resources\PriceListResource\Pages;

use Eclipse\Catalogue\Filament\Resources\PriceListResource;
use Eclipse\Catalogue\Traits\HandlesTenantData;
use Eclipse\Catalogue\Traits\HasPriceListForm;
use Eclipse\Catalogue\Traits\HasTenantFields;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class EditPriceList extends EditRecord
{
    use HandlesTenantData, HasPriceListForm, HasTenantFields;

    protected function getFormTenantFlags(): array
    {
        return ['is_active', 'is_default', 'is_default_purchase'];
    }

    protected function getFormMutuallyExclusiveFlagSets(): array
    {
        return [['is_default', 'is_default_purchase']];
    }

    protected static string $resource = PriceListResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components($this->buildPriceListFormSchema());
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $tenantFK = config('eclipse-catalogue.tenancy.foreign_key');

        if (! $tenantFK) {
            // No tenancy - load single record
            $priceListData = $this->record->priceListData()->first();

            if ($priceListData) {
                $data['is_active'] = $priceListData->is_active;
                $data['is_default'] = $priceListData->is_default;
                $data['is_default_purchase'] = $priceListData->is_default_purchase;
            }

            return $data;
        }

        // Load tenant-specific data
        $tenantData = [];
        $priceListData = $this->record->priceListData;

        foreach ($priceListData as $tenantRecord) {
            $tenantId = $tenantRecord->getAttribute($tenantFK);
            $tenantData[$tenantId] = [
                'is_active' => $tenantRecord->is_active,
                'is_default' => $tenantRecord->is_default,
                'is_default_purchase' => $tenantRecord->is_default_purchase,
            ];
        }

        $data['tenant_data'] = $tenantData;

        // Set the selected tenant to current tenant so the form shows properly
        $currentTenant = Filament::getTenant();
        $data['selected_tenant'] = $currentTenant?->id;

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        // Extract tenant data from form
        $tenantData = $this->extractTenantDataFromFormData($data);

        // Clean main record data
        $priceListData = $this->cleanFormDataForMainRecord($data);

        // Use the model's updateWithTenantData method
        $record->updateWithTenantData($priceListData, $tenantData);

        return $record;
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()
                ->action(function () {
                    // Store current tenant data before validation/saving
                    $this->storeCurrentTenantData();
                    $this->validateDefaultConstraintsBeforeSave();
                    $this->save();
                }),
            $this->getCancelFormAction(),
        ];
    }

    protected function resolveRecord($key): Model
    {
        // Load record without joins to avoid ambiguous column issues
        return static::getResource()::getModel()::withoutGlobalScopes()->findOrFail($key);
    }
}
