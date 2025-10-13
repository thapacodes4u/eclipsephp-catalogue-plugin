<?php

namespace Eclipse\Catalogue\Models;

use Eclipse\Catalogue\Factories\GroupFactory;
use Eclipse\Common\Foundation\Models\Scopes\ActiveScope;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

#[ScopedBy(ActiveScope::class)]
class Group extends Model
{
    use HasFactory;

    protected $table = 'pim_group';

    protected $fillable = [
        'code',
        'name',
        'is_active',
        'is_browsable',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_browsable' => 'boolean',
    ];

    /**
     * Scope: restrict by current tenant if tenancy is enabled and a tenant is selected.
     */
    public function scopeForCurrentTenant(Builder $query): Builder
    {
        $tenantFK = config('eclipse-catalogue.tenancy.foreign_key');
        $tenant = Filament::getTenant();

        if ($tenantFK && $tenant) {
            $query->where($tenantFK, $tenant->id);
        }

        return $query;
    }

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

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'pim_group_has_product', 'group_id', 'product_id')
            ->withPivot('sort')
            ->orderBy('pim_group_has_product.sort');
    }

    /**
     * Add a product to this group with optional sort order
     */
    public function addProduct(Product $product, ?int $sort = null): void
    {
        if ($this->hasProduct($product)) {
            return;
        }

        if ($sort === null) {
            $sort = $this->getNextSortOrder();
        }

        DB::table('pim_group_has_product')->insert([
            'group_id' => $this->id,
            'product_id' => $product->id,
            'sort' => $sort,
        ]);
    }

    /**
     * Remove a product from this group
     */
    public function removeProduct(Product $product): void
    {
        DB::table('pim_group_has_product')
            ->where('group_id', $this->id)
            ->where('product_id', $product->id)
            ->delete();
    }

    /**
     * Check if this group contains a specific product
     */
    public function hasProduct(Product $product): bool
    {
        return DB::table('pim_group_has_product')
            ->where('group_id', $this->id)
            ->where('product_id', $product->id)
            ->exists();
    }

    /**
     * Update the sort order for a product in this group
     */
    public function updateProductSort(Product $product, int $sort): void
    {
        DB::table('pim_group_has_product')
            ->where('group_id', $this->id)
            ->where('product_id', $product->id)
            ->update(['sort' => $sort]);
    }

    /**
     * Get the next available sort order for this group
     */
    public function getNextSortOrder(): int
    {
        $maxSort = DB::table('pim_group_has_product')
            ->where('group_id', $this->id)
            ->max('sort');

        return ($maxSort ?? 0) + 1;
    }

    /**
     * Reorder products in this group based on an array of product IDs
     */
    public function reorderProducts(array $productIds): void
    {
        foreach ($productIds as $index => $productId) {
            DB::table('pim_group_has_product')
                ->where('group_id', $this->id)
                ->where('product_id', $productId)
                ->update(['sort' => $index + 1]);
        }
    }

    /**
     * Get products count for this group
     */
    public function getProductsCountAttribute(): int
    {
        return DB::table('pim_group_has_product')
            ->where('group_id', $this->id)
            ->count();
    }

    protected static function newFactory(): GroupFactory
    {
        return GroupFactory::new();
    }

    /** @return BelongsTo<self> */
    public function site(): BelongsTo
    {
        $tenantModel = config('eclipse-catalogue.tenancy.model');

        return $this->belongsTo($tenantModel);
    }
}
