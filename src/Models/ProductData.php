<?php

namespace Eclipse\Catalogue\Models;

use Eclipse\Catalogue\Factories\ProductDataFactory;
use Eclipse\Core\Models\Site;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductData extends Model
{
    use HasFactory;

    protected $table = 'catalogue_product_data';

    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'category_id',
        'product_status_id',
        'sorting_label',
        'is_active',
        'available_from_date',
        'has_free_delivery',
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

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<ProductStatus> */
    public function status(): BelongsTo
    {
        return $this->belongsTo(ProductStatus::class, 'product_status_id');
    }

    /** @return BelongsTo<\Eclipse\Core\Models\Site, self> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'available_from_date' => 'datetime',
            'has_free_delivery' => 'boolean',
        ];
    }

    protected static function newFactory()
    {
        return ProductDataFactory::new();
    }
}
