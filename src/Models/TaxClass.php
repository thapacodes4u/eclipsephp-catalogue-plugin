<?php

namespace Eclipse\Catalogue\Models;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class TaxClass extends Model
{
    use SoftDeletes;

    protected $table = 'pim_tax_classes';

    public function getFillable(): array
    {
        $fillable = [
            'name',
            'description',
            'rate',
            'is_default',
        ];

        if (config('eclipse-catalogue.tenancy.foreign_key')) {
            $fillable[] = config('eclipse-catalogue.tenancy.foreign_key');
        }

        return $fillable;
    }

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'is_default' => 'boolean',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $category): void {
            // Set tenant foreign key, if configured
            $tenantModel = config('eclipse-catalogue.tenancy.model');
            $tenantFK = config('eclipse-catalogue.tenancy.foreign_key');

            if ($tenantModel && $tenantFK) {
                $tenant = Filament::getTenant();

                if (empty($tenant)) {
                    throw new RuntimeException('Tenancy is enabled, but no tenant is set');
                }

                $category->{$tenantFK} = $tenant->id;
            }
        });

        static::saving(function ($model) {
            // If this class is being set as default, unset all other defaults within the same tenant
            if ($model->is_default) {
                $query = static::where('is_default', true)
                    ->where('id', '!=', $model->id);

                // Add tenant scope if tenancy is configured
                $tenantFK = config('eclipse-catalogue.tenancy.foreign_key');
                $tenantId = $model->getAttribute($tenantFK);
                if ($tenantFK && $tenantId) {
                    $query->where($tenantFK, $tenantId);
                }

                $query->update(['is_default' => false]);
            }
        });

        static::deleting(function ($model) {
            // Prevent deletion of default class
            if ($model->is_default) {
                throw ValidationException::withMessages([
                    'is_default' => 'Cannot delete the default tax class.',
                ]);
            }
        });
    }

    /**
     * Get the default tax class for the current tenant
     */
    public static function getDefault(?int $tenantId = null): ?self
    {
        $query = static::where('is_default', true);

        // Add tenant scope if tenancy is configured
        $tenantFK = config('eclipse-catalogue.tenancy.foreign_key');
        $currentTenantId = $tenantId ?: Filament::getTenant()?->id;
        if ($tenantFK && $currentTenantId) {
            $query->where($tenantFK, $currentTenantId);
        }

        return $query->first();
    }

    /**
     * Check if this is the default class
     */
    public function isDefault(): bool
    {
        return $this->is_default;
    }

    public function tenant(): BelongsTo
    {
        $tenantModel = config('eclipse-catalogue.tenancy.model');
        $tenantFK = config('eclipse-catalogue.tenancy.foreign_key');

        return $this->belongsTo($tenantModel, $tenantFK);
    }
}
