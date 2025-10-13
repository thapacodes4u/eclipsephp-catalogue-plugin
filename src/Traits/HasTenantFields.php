<?php

namespace Eclipse\Catalogue\Traits;

use Filament\Facades\Filament;

trait HasTenantFields
{
    /**
     * Check if tenancy is enabled
     */
    protected function isTenancyEnabled(): bool
    {
        return (bool) config('eclipse-catalogue.tenancy.foreign_key');
    }

    /**
     * Get the current tenant ID
     */
    protected function getCurrentTenantId(): ?int
    {
        return Filament::getTenant()?->id;
    }
}
