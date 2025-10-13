<?php

namespace Eclipse\Catalogue\Models;

use Eclipse\Catalogue\Factories\ProductTypeDataFactory;
use Eclipse\Core\Models\Site;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductTypeData extends Model
{
    use HasFactory;

    protected $table = 'pim_product_type_data';

    public $timestamps = false;

    protected $fillable = [
        'product_type_id',
        'is_active',
        'is_default',
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
     * Parent ProductType relation.
     */
    public function productType(): BelongsTo
    {
        return $this->belongsTo(ProductType::class, 'product_type_id');
    }

    /** @return BelongsTo<\Eclipse\Core\Models\Site, self> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * Ensure boolean flags are cast properly.
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    protected static function newFactory()
    {
        return ProductTypeDataFactory::new();
    }
}
