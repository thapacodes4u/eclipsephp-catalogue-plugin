<?php

namespace Eclipse\Catalogue\Models;

use Eclipse\Catalogue\Factories\ProductTypeFactory;
use Eclipse\Catalogue\Traits\HasTenantScopedData;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class ProductType extends Model
{
    use HasFactory, HasTenantScopedData, HasTranslations, SoftDeletes;

    protected $table = 'pim_product_types';

    protected $fillable = [
        'name',
        'code',
    ];

    public array $translatable = [
        'name',
    ];

    protected $appends = [
        'is_active',
        'is_default',
    ];

    protected static string $tenantDataRelation = 'productTypeData';

    protected static string $tenantDataModel = ProductTypeData::class;

    protected static array $tenantFlags = ['is_active', 'is_default'];

    protected static array $mutuallyExclusiveFlagSets = [];

    protected static array $uniqueFlagsPerTenant = ['is_default'];

    /**
     * Get all per-tenant data rows for this product type.
     */
    public function productTypeData(): HasMany
    {
        return $this->hasMany(ProductTypeData::class, 'product_type_id');
    }

    /**
     * Get all properties assigned to this product type.
     */
    public function properties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class, 'pim_product_type_has_property')
            ->withPivot('sort')
            ->withTimestamps()
            ->orderByPivot('sort');
    }

    /**
     * Find the default product type for a tenant.
     * If tenantId is omitted, the current Filament tenant is used.
     */
    public static function getDefault(?int $tenantId = null): ?self
    {
        $tenantFK = config('eclipse-catalogue.tenancy.foreign_key');
        $currentTenantId = $tenantId ?: Filament::getTenant()?->id;

        $query = static::whereHas('productTypeData', function ($q) use ($tenantFK, $currentTenantId) {
            $q->where('is_default', true);
            if ($tenantFK && $currentTenantId) {
                $q->where($tenantFK, $currentTenantId);
            }
        });

        return $query->first();
    }

    /**
     * Accessor for the is_active attribute.
     * Reads from current tenant data; defaults to true when missing.
     */
    public function getIsActiveAttribute(): bool
    {
        return $this->getTenantFlagValue('is_active');
    }

    /**
     * Accessor for the is_default attribute.
     * Reads from current tenant data; defaults to false when missing.
     */
    public function getIsDefaultAttribute(): bool
    {
        return $this->getTenantFlagValue('is_default');
    }

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (ProductType $productType) {
            // Auto-assign global properties to new product types
            $globalProperties = Property::where('is_global', true)->get();
            foreach ($globalProperties as $property) {
                $productType->properties()->attach($property->id, ['sort' => 0]);
            }
        });
    }

    protected static function newFactory(): ProductTypeFactory
    {
        return ProductTypeFactory::new();
    }
}
