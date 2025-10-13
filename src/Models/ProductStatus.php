<?php

namespace Eclipse\Catalogue\Models;

use Eclipse\Catalogue\Enums\StructuredData\ItemAvailability;
use Eclipse\Catalogue\Factories\ProductStatusFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Spatie\Translatable\HasTranslations;

class ProductStatus extends Model
{
    use HasFactory, HasTranslations;

    protected $table = 'pim_product_statuses';

    protected $fillable = [
        'code',
        'title',
        'description',
        'label_type',
        'shown_in_browse',
        'allow_price_display',
        'allow_sale',
        'is_default',
        'priority',
        'sd_item_availability',
        'skip_stock_qty_check',
    ];

    public array $translatable = [
        'title',
        'description',
    ];

    /**
     * Include the tenant foreign key in fillable when tenancy is on.
     */
    public function getFillable(): array
    {
        $fillable = $this->fillable;
        if (config('eclipse-catalogue.tenancy.foreign_key')) {
            $fillable[] = config('eclipse-catalogue.tenancy.foreign_key');
        }

        return $fillable;
    }

    /**
     * Define the casts for the model.
     */
    protected function casts(): array
    {
        return [
            'title' => 'array',
            'description' => 'array',
            'shown_in_browse' => 'boolean',
            'allow_price_display' => 'boolean',
            'allow_sale' => 'boolean',
            'is_default' => 'boolean',
            'priority' => 'integer',
            'skip_stock_qty_check' => 'boolean',
            'sd_item_availability' => ItemAvailability::class,
        ];
    }

    protected static function newFactory(): ProductStatusFactory
    {
        return ProductStatusFactory::new();
    }

    /** @return void */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            // Validate code length
            if (strlen($model->code) > 20) {
                throw new InvalidArgumentException('Code must not exceed 20 characters.');
            }

            // Validate required fields
            if (empty($model->title) || empty($model->label_type) || empty($model->priority) || empty($model->sd_item_availability)) {
                throw new InvalidArgumentException('Required fields cannot be empty.');
            }
        });

        static::creating(function ($model) {
            // Enforce allow_sale = false when allow_price_display = false
            if (! $model->allow_price_display) {
                $model->allow_sale = false;
            }

            // If this status is being set as default, unset any existing default for the same site
            if ($model->is_default) {
                $tenantFK = config('eclipse-catalogue.tenancy.foreign_key');
                if ($tenantFK && isset($model->$tenantFK)) {
                    static::where($tenantFK, $model->$tenantFK)
                        ->where('is_default', true)
                        ->update(['is_default' => false]);
                } elseif (! $tenantFK) {
                    // When tenancy is disabled, unset any existing default globally
                    static::where('is_default', true)
                        ->update(['is_default' => false]);
                }
            }
        });

        static::updating(function ($model) {
            // Enforce allow_sale = false when allow_price_display = false
            if ($model->isDirty('allow_price_display') && ! $model->allow_price_display) {
                $model->allow_sale = false;
            }

            // If this status is being set as default, unset any existing default for the same site
            if ($model->isDirty('is_default') && $model->is_default) {
                $tenantFK = config('eclipse-catalogue.tenancy.foreign_key');
                if ($tenantFK && isset($model->$tenantFK)) {
                    static::where($tenantFK, $model->$tenantFK)
                        ->where('is_default', true)
                        ->where('id', '!=', $model->id)
                        ->update(['is_default' => false]);
                } elseif (! $tenantFK) {
                    // When tenancy is disabled, unset any existing default globally
                    static::where('is_default', true)
                        ->where('id', '!=', $model->id)
                        ->update(['is_default' => false]);
                }
            }
        });
    }
}
