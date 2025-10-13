<?php

namespace Eclipse\Catalogue\Models;

use Eclipse\Catalogue\Enums\PropertyInputType;
use Eclipse\Catalogue\Factories\ProductFactory;
use Eclipse\Catalogue\Models\Product\Price;
use Eclipse\Catalogue\Traits\HasTenantScopedData;
use Eclipse\Common\Foundation\Models\IsSearchable;
use Eclipse\World\Models\Country;
use Eclipse\World\Models\TariffCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Translatable\HasTranslations;

class Product extends Model implements HasMedia
{
    use HasFactory, HasTenantScopedData, HasTranslations, InteractsWithMedia, IsSearchable, SoftDeletes;

    protected $table = 'catalogue_products';

    protected $fillable = [
        'code',
        'barcode',
        'manufacturers_code',
        'suppliers_code',
        'net_weight',
        'gross_weight',
        'name',
        'product_type_id',
        'category_id',
        'short_description',
        'description',
        'origin_country_id',
        'tariff_code_id',
        'meta_description',
        'meta_title',
    ];

    public array $translatable = [
        'name',
        'short_description',
        'description',
        'meta_title',
        'meta_description',
    ];

    protected $casts = [
        'name' => 'array',
        'short_description' => 'array',
        'description' => 'array',
        'meta_title' => 'array',
        'meta_description' => 'array',
        'deleted_at' => 'datetime',
        'product_type_id' => 'integer',
        'available_from_date' => 'datetime',
        'is_active' => 'boolean',
        'has_free_delivery' => 'boolean',
    ];

    protected $appends = [
        'is_active',
        'has_free_delivery',
        'available_from_date',
        'sorting_label',
    ];

    protected static string $tenantDataRelation = 'productData';

    protected static string $tenantDataModel = ProductData::class;

    protected static array $tenantFlags = ['is_active', 'has_free_delivery'];

    protected static array $mutuallyExclusiveFlagSets = [];

    protected static array $uniqueFlagsPerTenant = [];

    protected static array $tenantAttributes = [
        'sorting_label',
        'available_from_date',
        'category_id',
        'product_status_id',
    ];

    public function status(): ?ProductStatus
    {
        return $this->currentTenantData()?->status;
    }

    public function category(): ?Category
    {
        return $this->currentTenantData()?->category;
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(ProductType::class, 'product_type_id');
    }

    public function propertyValues(): BelongsToMany
    {
        return $this->belongsToMany(PropertyValue::class, 'catalogue_product_has_property_value', 'product_id', 'property_value_id')
            ->withTimestamps();
    }

    public function customPropertyValues(): HasMany
    {
        return $this->hasMany(CustomPropertyValue::class);
    }

    public function customProperties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class, 'catalogue_product_has_custom_prop_value', 'product_id', 'property_id')
            ->withPivot('value')
            ->withTimestamps();
    }

    public function originCountry(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'origin_country_id', 'id');
    }

    public function tariffCode(): BelongsTo
    {
        return $this->belongsTo(TariffCode::class, 'tariff_code_id');
    }

    /**
     * Get all per-tenant data rows for this product.
     */
    public function productData(): HasMany
    {
        return $this->hasMany(ProductData::class, 'product_id');
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'pim_group_has_product', 'product_id', 'group_id')
            ->withPivot('sort');
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->getTenantFlagValue('is_active');
    }

    public function getHasFreeDeliveryAttribute(): bool
    {
        return $this->getTenantFlagValue('has_free_delivery');
    }

    /**
     * Prices relationship.
     */
    public function prices(): HasMany
    {
        return $this->hasMany(Price::class);
    }

    public function getAvailableFromDateAttribute()
    {
        return $this->currentTenantData()?->available_from_date;
    }

    public function getSortingLabelAttribute(): ?string
    {
        return $this->currentTenantData()?->sorting_label;
    }

    public function getCustomPropertyValue(Property $property): ?CustomPropertyValue
    {
        return $this->customPropertyValues()->where('property_id', $property->id)->first();
    }

    public function setCustomPropertyValue(Property $property, $value): void
    {
        $this->customPropertyValues()->updateOrCreate(
            ['property_id' => $property->id],
            ['value' => $value]
        );
    }

    public function getCustomPropertyValueFormatted(Property $property): string
    {
        $customValue = $this->getCustomPropertyValue($property);

        return $customValue ? $customValue->getFormattedValue() : '';
    }

    public function getCustomPropertyValuesForSearch(): string
    {
        $customValues = $this->customPropertyValues()->with('property')->get();
        if ($customValues->isEmpty()) {
            return '';
        }

        $searchValues = [];
        foreach ($customValues as $customValue) {
            $property = $customValue->property;
            $value = $customValue->getFormattedValue();
            if (! empty($value)) {
                $propertyName = $property->internal_name ?: (is_array($property->name)
                    ? ($property->name[app()->getLocale()] ?? reset($property->name))
                    : $property->name);
                $searchValues[] = "{$propertyName} {$value}";
            }
        }

        return implode(' ', $searchValues);
    }

    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp'])
            ->useDisk('public');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(300)
            ->height(300)
            ->sharpen(10)
            ->nonQueued();

        $this->addMediaConversion('preview')
            ->width(500)
            ->height(500)
            ->sharpen(10)
            ->nonQueued();
    }

    public function getCoverImageAttribute()
    {
        return $this->getMedia('images')->firstWhere('custom_properties.is_cover', true)
            ?? $this->getFirstMedia('images');
    }

    /**
     * Get the indexable data array for the model.
     */
    public function toSearchableArray(): array
    {
        $data = $this->createSearchableArray();

        if ($this->tariffCode) {
            $data['tariff_code'] = $this->tariffCode->code;
        }

        $customValues = $this->customPropertyValues()->with('property')->get();

        foreach ($customValues as $customValue) {
            $property = $customValue->property;

            if (! $property || ! $property->isCustomType() || ! in_array($property->input_type, [PropertyInputType::STRING->value, PropertyInputType::TEXT->value])) {
                continue;
            }

            $value = $customValue->getFormattedValue();
            if (empty($value)) {
                continue;
            }

            $codeKey = $property->code ?: (string) $property->id;

            if ($property->supportsMultilang() && is_array($customValue->value)) {
                foreach ($customValue->value as $localeId => $localeValue) {
                    if (! empty($localeValue)) {
                        $data["cprop_{$codeKey}_{$localeId}"] = strip_tags($localeValue);
                    }
                }
            } else {
                $data["cprop_{$codeKey}"] = strip_tags($value);
            }
        }

        return $data;
    }

    public static function getTypesenseSettings(): array
    {
        return [
            'collection-schema' => [
                'fields' => [
                    [
                        'name' => 'id',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'code',
                        'type' => 'string',
                        'optional' => true,
                    ],
                    [
                        'name' => 'barcode',
                        'type' => 'string',
                        'optional' => true,
                    ],
                    [
                        'name' => 'created_at',
                        'type' => 'int64',
                    ],
                    [
                        'name' => 'name_.*',
                        'type' => 'string',
                        'optional' => true,
                    ],
                    [
                        'name' => 'short_description_.*',
                        'type' => 'string',
                        'optional' => true,
                    ],
                    [
                        'name' => 'description_.*',
                        'type' => 'string',
                        'optional' => true,
                    ],
                    [
                        'name' => 'cprop_.*',
                        'type' => 'string',
                        'optional' => true,
                    ],
                    [
                        'name' => '__soft_deleted',
                        'type' => 'int32',
                        'optional' => true,
                    ],
                    [
                        'name' => 'tariff_code',
                        'type' => 'string',
                        'optional' => true,
                    ],
                ],
            ],
            'search-parameters' => [
                'query_by' => implode(', ', [
                    'code',
                    'barcode',
                    'name_*',
                    'short_description_*',
                    'description_*',
                    'tariff_code',
                    'cprop_*',
                ]),
            ],
        ];
    }
}
