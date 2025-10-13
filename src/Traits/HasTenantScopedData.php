<?php

namespace Eclipse\Catalogue\Traits;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;
use ReflectionClass;

/**
 * Adds per-tenant data behavior to an Eloquent model.
 *
 * How to use:
 * - Add this trait to the base model (e.g., Product, PriceList, ProductType).
 * - Define the following protected static properties on the model:
 *   - $tenantDataRelation: string name of the hasMany relation method (e.g., 'productData').
 *   - $tenantDataModel: class-string of the data model (e.g., ProductData::class).
 *   - $tenantFlags: array of boolean field names stored per-tenant (e.g., ['is_active']).
 *   - $mutuallyExclusiveFlagSets: array of string[] sets that cannot be true simultaneously.
 *   - $uniqueFlagsPerTenant: array of flags that must be unique per tenant (others will be reset to false).
 *   - $tenantAttributes: optional array of additional per-tenant, non-boolean fields (e.g., dates, strings).
 *
 * Responsibilities:
 * - Exposes helpers to read/write per-tenant data for the current Filament tenant.
 * - Enforces constraints (mutually exclusive flags, unique flags per tenant) during create/update.
 * - Filters persisted payloads to only allow configured flags/attributes and respect data-model fillable.
 */
trait HasTenantScopedData
{
    /**
     * Configuration for tenant-scoped behavior.
     * Override these properties in your model to customize behavior.
     *
     * Required properties that must be defined in the model:
     * - $tenantDataRelation: The name of the relationship method
     * - $tenantDataModel: The class name of the tenant data model
     * - $tenantFlags: Array of flag names to handle
     * - $mutuallyExclusiveFlagSets: Array of arrays containing mutually exclusive flags
     * - $uniqueFlagsPerTenant: Array of flags that should be unique per tenant
     */

    /**
     * Get all per-tenant data rows for this model.
     */
    public function getTenantDataRelation(): HasMany
    {
        $relationName = $this->getTenantDataRelationName();

        return $this->$relationName();
    }

    /**
     * Resolve the tenant data relation name from the model via its static configuration.
     */
    protected function getTenantDataRelationName(): string
    {
        $reflection = new ReflectionClass(static::class);
        $property = $reflection->getProperty('tenantDataRelation');
        $property->setAccessible(true);

        return $property->getValue();
    }

    /**
     * Resolve the tenant data model class from the model via its static configuration.
     */
    protected function getTenantDataModelClass(): string
    {
        $reflection = new ReflectionClass(static::class);
        $property = $reflection->getProperty('tenantDataModel');
        $property->setAccessible(true);

        return $property->getValue();
    }

    /**
     * Return boolean flag keys that are stored per tenant.
     */
    protected function getTenantFlags(): array
    {
        $reflection = new ReflectionClass(static::class);
        $property = $reflection->getProperty('tenantFlags');
        $property->setAccessible(true);

        return $property->getValue() ?? [];
    }

    /**
     * Return sets of flags that cannot be simultaneously true.
     */
    protected function getMutuallyExclusiveFlagSets(): array
    {
        $reflection = new ReflectionClass(static::class);
        $property = $reflection->getProperty('mutuallyExclusiveFlagSets');
        $property->setAccessible(true);

        return $property->getValue() ?? [];
    }

    /**
     * Static variant of mutually exclusive flag sets.
     */
    protected static function getStaticMutuallyExclusiveFlagSets(): array
    {
        $reflection = new ReflectionClass(static::class);
        $property = $reflection->getProperty('mutuallyExclusiveFlagSets');
        $property->setAccessible(true);

        return $property->getValue() ?? [];
    }

    /**
     * Return flags that must be unique per tenant (others will be reset to false on update).
     */
    protected function getUniqueFlagsPerTenant(): array
    {
        $reflection = new ReflectionClass(static::class);
        $property = $reflection->getProperty('uniqueFlagsPerTenant');
        $property->setAccessible(true);

        return $property->getValue() ?? [];
    }

    /**
     * Return additional per-tenant attributes (non-boolean) to be stored alongside flags.
     */
    protected function getTenantAttributes(): array
    {
        $reflection = new ReflectionClass(static::class);
        if ($reflection->hasProperty('tenantAttributes')) {
            $property = $reflection->getProperty('tenantAttributes');
            $property->setAccessible(true);

            return $property->getValue() ?? [];
        }

        return [];
    }

    /**
     * Filter a per-tenant payload to allowed keys and intersect with the data-model fillable.
     */
    protected function filterTenantDataForPersistence(array $tenantData): array
    {
        $tenantFlags = $this->getTenantFlags();
        $tenantAttrs = $this->getTenantAttributes();
        $allowedKeys = array_flip(array_merge($tenantFlags, $tenantAttrs));

        // First pass: only keep allowed logical keys
        $filtered = array_intersect_key($tenantData, $allowedKeys);

        // Second pass: ensure only fillable keys for data model persist
        $dataModelClass = $this->getTenantDataModelClass();
        /** @var Model $dataModel */
        $dataModel = new $dataModelClass;
        $fillable = array_flip($dataModel->getFillable());

        return array_intersect_key($filtered, $fillable);
    }

    /**
     * Get the per-tenant data for the current Filament tenant (if any).
     * Falls back to the first data row when tenancy is disabled.
     */
    public function currentTenantData()
    {
        $tenantFK = config('eclipse-catalogue.tenancy.foreign_key');
        $tenantId = Filament::getTenant()?->id;

        if ($tenantFK && $tenantId) {
            return $this->getTenantDataRelation()->where($tenantFK, $tenantId)->first();
        }

        return $this->getTenantDataRelation()->first();
    }

    /**
     * Get per-tenant data for a specific tenant ID; defaults to current tenant when omitted.
     */
    public function getTenantData(?int $tenantId = null)
    {
        $tenantFK = config('eclipse-catalogue.tenancy.foreign_key');
        $targetTenantId = $tenantId ?: Filament::getTenant()?->id;

        if ($tenantFK && $targetTenantId) {
            return $this->getTenantDataRelation()->where($tenantFK, $targetTenantId)->first();
        }

        return $this->getTenantDataRelation()->first();
    }

    /**
     * Intercept attribute access for configured tenant flags and resolve from current tenant data.
     */
    public function getAttribute($key)
    {
        // Check if this is one of our tenant flags
        if (in_array($key, $this->getTenantFlags())) {
            return $this->getTenantFlagValue($key);
        }

        return parent::getAttribute($key);
    }

    /**
     * Resolve a boolean flag value from current tenant data; defaults to false if absent.
     */
    protected function getTenantFlagValue(string $flag)
    {
        if (isset($this->attributes[$flag])) {
            return $this->attributes[$flag];
        }

        $tenantData = $this->currentTenantData();

        return $tenantData ? (bool) $tenantData->$flag : false;
    }

    /**
     * Create a new model together with its per-tenant settings.
     */
    public static function createWithTenantData(array $mainData, array $tenantData = []): self
    {
        $tenantFK = config('eclipse-catalogue.tenancy.foreign_key');

        // Create the main record
        $model = static::create($mainData);

        if (! $tenantFK) {
            // No tenancy: create a single data row with provided or default flags
            $tenantFlags = $model->getTenantFlags();
            $tenantAttrs = $model->getTenantAttributes();
            $allowedKeys = array_merge($tenantFlags, $tenantAttrs);
            $singleTenantData = $tenantData ?: array_fill_keys($tenantFlags, true);

            // Set defaults for specific flags
            foreach ($tenantFlags as $flag) {
                if (! isset($singleTenantData[$flag])) {
                    $singleTenantData[$flag] = in_array($flag, ['is_active']) ? true : false;
                }
            }

            // Filter to allowed keys only and respect data model fillable
            $singleTenantData = $model->filterTenantDataForPersistence($singleTenantData);

            // Enforce invariants
            $model->handleDefaultConstraints($singleTenantData, null);

            $dataModelClass = $model->getTenantDataModelClass();
            $relationKey = $model->getTenantDataRelation()->getForeignKeyName();

            $dataModelClass::create([
                $relationKey => $model->id,
                ...$singleTenantData,
            ]);

            return $model;
        }

        // Tenancy enabled: create a data row for each tenant
        $tenantModel = config('eclipse-catalogue.tenancy.model');
        $tenants = $tenantModel::all();

        foreach ($tenants as $tenant) {
            $tenantId = $tenant->id;

            // Use provided data if available; otherwise apply safe defaults
            $tenantFlags = $model->getTenantFlags();
            $tenantAttrs = $model->getTenantAttributes();
            $allowedKeys = array_merge($tenantFlags, $tenantAttrs);
            $tenantSpecificData = $tenantData[$tenantId] ?? array_fill_keys($tenantFlags, false);

            // Set defaults for specific flags
            foreach ($tenantFlags as $flag) {
                if (! isset($tenantSpecificData[$flag])) {
                    $tenantSpecificData[$flag] = in_array($flag, ['is_active']) ? true : false;
                }
            }

            // Filter to allowed keys only and respect data model fillable
            $tenantSpecificData = $model->filterTenantDataForPersistence($tenantSpecificData);

            // Enforce invariants for this tenant
            $model->handleDefaultConstraints($tenantSpecificData, $tenantId);

            $dataModelClass = $model->getTenantDataModelClass();
            $relationKey = $model->getTenantDataRelation()->getForeignKeyName();

            $dataModelClass::create([
                $relationKey => $model->id,
                $tenantFK => $tenantId,
                ...$tenantSpecificData,
            ]);
        }

        return $model;
    }

    /**
     * Update the base model row and its per-tenant settings.
     */
    public function updateWithTenantData(array $mainData = [], array $tenantData = []): self
    {
        // Update main record if data provided
        if (! empty($mainData)) {
            $this->update($mainData);
        }

        $tenantFK = config('eclipse-catalogue.tenancy.foreign_key');

        if (! $tenantFK) {
            // No tenancy: upsert a single data row
            if (! empty($tenantData)) {
                $this->handleDefaultConstraints($tenantData, null);

                $dataModelClass = static::$tenantDataModel;
                $relationKey = $this->getTenantDataRelation()->getForeignKeyName();

                // Filter again to respect data model fillable
                $tenantData = $this->filterTenantDataForPersistence($tenantData);

                $dataModelClass::updateOrCreate(
                    [$relationKey => $this->id],
                    $tenantData
                );
            }

            return $this;
        }

        // Tenancy enabled: update/create records for all tenants
        $tenantModel = config('eclipse-catalogue.tenancy.model');
        $tenants = $tenantModel::all();

        foreach ($tenants as $tenant) {
            $tenantId = $tenant->id;
            $tenantFlags = $this->getTenantFlags();
            $tenantSpecificData = $tenantData[$tenantId] ?? array_fill_keys($tenantFlags, false);

            // Set defaults for specific flags
            foreach ($tenantFlags as $flag) {
                if (! isset($tenantSpecificData[$flag])) {
                    $tenantSpecificData[$flag] = in_array($flag, ['is_active']) ? true : false;
                }
            }

            $this->handleDefaultConstraints($tenantSpecificData, $tenantId);

            $dataModelClass = $this->getTenantDataModelClass();
            $relationKey = $this->getTenantDataRelation()->getForeignKeyName();

            // Filter again to respect data model fillable
            $filteredTenantSpecificData = $this->filterTenantDataForPersistence($tenantSpecificData);

            $dataModelClass::updateOrCreate(
                [
                    $relationKey => $this->id,
                    $tenantFK => $tenantId,
                ],
                $filteredTenantSpecificData
            );
        }

        return $this;
    }

    /**
     * Enforce constraints for unique flags and mutually exclusive flag sets.
     */
    public function handleDefaultConstraints(array &$tenantData, ?int $tenantId): void
    {
        $tenantFK = config('eclipse-catalogue.tenancy.foreign_key');

        // Check mutually exclusive flag sets
        foreach ($this->getMutuallyExclusiveFlagSets() as $exclusiveSet) {
            $activeFlags = array_filter($exclusiveSet, fn ($flag) => $tenantData[$flag] ?? false);

            if (count($activeFlags) > 1) {
                $errorKey = $tenantId ? "tenant_data.{$tenantId}" : '';
                $errors = [];
                foreach ($activeFlags as $flag) {
                    $errors[$errorKey ? "{$errorKey}.{$flag}" : $flag] = 'These options cannot be enabled simultaneously.';
                }
                throw ValidationException::withMessages($errors);
            }
        }

        // Handle unique flags per tenant
        foreach ($this->getUniqueFlagsPerTenant() as $uniqueFlag) {
            if ($tenantData[$uniqueFlag] ?? false) {
                $dataModelClass = $this->getTenantDataModelClass();
                $relationKey = $this->getTenantDataRelation()->getForeignKeyName();

                $query = $dataModelClass::where($uniqueFlag, true);

                // Exclude current record if it exists (for updates)
                if ($this->exists) {
                    $query->where($relationKey, '!=', $this->id);
                }

                if ($tenantFK && $tenantId) {
                    $query->where($tenantFK, $tenantId);
                }

                $query->update([$uniqueFlag => false]);
            }
        }
    }

    /**
     * Validate tenant data constraints prior to save; throws ValidationException on conflict.
     */
    public static function validateTenantDataConstraints(array $tenantData): void
    {
        $tenantFK = config('eclipse-catalogue.tenancy.foreign_key');

        if (! $tenantFK) {
            // No tenancy - validate simple fields
            foreach (static::getStaticMutuallyExclusiveFlagSets() as $exclusiveSet) {
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
            foreach (static::getStaticMutuallyExclusiveFlagSets() as $exclusiveSet) {
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
}
