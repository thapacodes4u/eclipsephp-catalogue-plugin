<?php

namespace Eclipse\Catalogue\Traits;

use Illuminate\Validation\ValidationException;

/**
 * Page-level helpers for Filament resources to handle per-tenant form state.
 *
 * Attach this trait to Filament Page classes (e.g., EditRecord/CreateRecord) and implement:
 * - getFormTenantFlags(): returns the list of boolean flags used in the form.
 * - getFormMutuallyExclusiveFlagSets(): returns sets of mutually exclusive flags.
 *
 * Responsibilities:
 * - Persist the currently selected tenant's sub-state into a hidden all_tenant_data field.
 * - Validate mutually exclusive constraints before saving.
 * - Extract a complete tenant_data payload from the form (merging live state and stored snapshots).
 * - Remove UI-only fields prior to persisting the base model.
 */
trait HandlesTenantData
{
    /**
     * List of boolean flags used in the form (override in the page).
     */
    protected function getFormTenantFlags(): array
    {
        return $this->formTenantFlags ?? ['is_active'];
    }

    /**
     * Sets of flags that cannot be true simultaneously (override in the page).
     */
    protected function getFormMutuallyExclusiveFlagSets(): array
    {
        return $this->formMutuallyExclusiveFlagSets ?? [];
    }

    /**
     * Persist the selected tenant's sub-state into hidden all_tenant_data to survive tenant switches.
     */
    protected function storeCurrentTenantData(): void
    {
        $formData = $this->form->getState();
        $selectedTenant = $formData['selected_tenant'] ?? null;

        if ($selectedTenant && config('eclipse-catalogue.tenancy.foreign_key')) {
            $currentData = $formData['tenant_data'][$selectedTenant] ?? [];

            $allTenantData = $formData['all_tenant_data'] ?? [];
            $allTenantData[$selectedTenant] = $currentData;

            $this->form->fill(['all_tenant_data' => $allTenantData]);
        }
    }

    /**
     * Validate mutually exclusive constraints before saving.
     */
    protected function validateDefaultConstraintsBeforeSave(): void
    {
        $data = $this->form->getState();
        $tenantFK = config('eclipse-catalogue.tenancy.foreign_key');

        if (! $tenantFK) {
            // No tenancy - validate simple fields
            $tenantData = [];
            foreach ($this->getFormTenantFlags() as $flag) {
                $tenantData[$flag] = $data[$flag] ?? $this->getDefaultValueForFlag($flag);
            }

            $this->validateTenantDataConstraints($tenantData);

            return;
        }

        // Validate tenant data (multi-tenant)
        $tenantData = $data['tenant_data'] ?? [];
        $this->validateTenantDataConstraints($tenantData);

        // Handle form state for first error tenant (for UI feedback)
        $firstErrorTenantId = $this->getFirstErrorTenantId($tenantData);
        if ($firstErrorTenantId) {
            $this->form->fill(['selected_tenant' => $firstErrorTenantId]);
        }
    }

    /**
     * Return the first tenant ID that violates mutually exclusive sets (for UI focus).
     */
    private function getFirstErrorTenantId(array $tenantData): ?int
    {
        foreach ($tenantData as $tenantId => $tenantSpecificData) {
            foreach ($this->getFormMutuallyExclusiveFlagSets() as $exclusiveSet) {
                $activeFlags = array_filter($exclusiveSet, fn ($flag) => $tenantSpecificData[$flag] ?? false);
                if (count($activeFlags) > 1) {
                    return $tenantId;
                }
            }
        }

        return null;
    }

    /**
     * Build the final tenant_data payload by merging stored snapshots with current live state.
     */
    protected function extractTenantDataFromFormData(array $data): array
    {
        $tenantFK = config('eclipse-catalogue.tenancy.foreign_key');

        if (! $tenantFK) {
            // No tenancy - return simple fields
            $result = [];
            foreach ($this->getFormTenantFlags() as $flag) {
                $result[$flag] = $data[$flag] ?? $this->getDefaultValueForFlag($flag);
            }

            return $result;
        }

        // Get stored tenant data and current form data
        $storedTenantData = $data['all_tenant_data'] ?? [];
        $currentTenantData = $data['tenant_data'] ?? [];
        $selectedTenant = $data['selected_tenant'] ?? null;

        // Merge stored data with current form data
        $result = $storedTenantData;

        // If we have a selected tenant, merge current tenant form data OVER stored data
        // to avoid dropping non-dehydrated keys (e.g., extra attributes) for the selected tenant.
        if ($selectedTenant) {
            $storedForSelected = $storedTenantData[$selectedTenant] ?? [];
            $currentForSelected = $currentTenantData[$selectedTenant] ?? [];
            $result[$selectedTenant] = array_merge($storedForSelected, $currentForSelected);
        }

        return $result;
    }

    /**
     * Strip UI-only fields from the form payload prior to saving the base model.
     */
    protected function cleanFormDataForMainRecord(array $data): array
    {
        $tenantFK = config('eclipse-catalogue.tenancy.foreign_key');

        if (! $tenantFK) {
            // Remove simple tenant fields
            foreach ($this->getFormTenantFlags() as $flag) {
                unset($data[$flag]);
            }
        } else {
            // Remove tenant UI fields
            unset($data['tenant_data'], $data['selected_tenant'], $data['all_tenant_data']);
        }

        return $data;
    }

    /**
     * Validate tenant data constraints; throws on conflicts.
     */
    protected function validateTenantDataConstraints(array $tenantData): void
    {
        $tenantFK = config('eclipse-catalogue.tenancy.foreign_key');

        if (! $tenantFK) {
            // No tenancy - validate simple fields
            foreach ($this->getFormMutuallyExclusiveFlagSets() as $exclusiveSet) {
                $activeFlags = array_filter($exclusiveSet, fn ($flag) => $tenantData[$flag] ?? false);

                if (count($activeFlags) > 1) {
                    $errors = [];
                    foreach ($activeFlags as $flag) {
                        $errors[$flag] = 'These options cannot be enabled simultaneously.';
                    }
                    throw ValidationException::withMessages($errors);
                }
            }

            return;
        }

        // Validate tenant data
        $errors = [];
        $firstErrorTenantId = null;

        foreach ($tenantData as $tenantId => $tenantSpecificData) {
            foreach ($this->getFormMutuallyExclusiveFlagSets() as $exclusiveSet) {
                $activeFlags = array_filter($exclusiveSet, fn ($flag) => $tenantSpecificData[$flag] ?? false);

                if (count($activeFlags) > 1) {
                    $tenantModel = config('eclipse-catalogue.tenancy.model');
                    $tenant = $tenantModel::find($tenantId);
                    $tenantName = $tenant ? $tenant->name : "Tenant {$tenantId}";

                    if (! $firstErrorTenantId) {
                        $firstErrorTenantId = $tenantId;
                    }

                    foreach ($activeFlags as $flag) {
                        $errors["tenant_data.{$tenantId}.{$flag}"] = "These options cannot be enabled simultaneously for {$tenantName}.";
                    }
                }
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Default value for a specific flag (used when not provided).
     */
    protected function getDefaultValueForFlag(string $flag): bool
    {
        return in_array($flag, ['is_active']) ? true : false;
    }
}
