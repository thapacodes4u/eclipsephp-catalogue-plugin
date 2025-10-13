<?php

use Eclipse\Catalogue\Filament\Resources\ProductResource\Pages\ListProducts;
use Eclipse\Catalogue\Models\Category;
use Eclipse\Catalogue\Models\Product;
use Eclipse\Catalogue\Models\ProductData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->setUpSuperAdmin();
});

it('can filter products by category in table', function (): void {
    $electronics = Category::factory()->create(['name' => 'Electronics']);
    $books = Category::factory()->create(['name' => 'Books']);

    $laptop = Product::factory()->create([
        'name' => 'Gaming Laptop',
    ]);
    $novel = Product::factory()->create([
        'name' => 'Science Fiction Novel',
    ]);
    $orphanProduct = Product::factory()->create([
        'name' => 'No Category Product',
    ]);

    $tenantFK = config('eclipse-catalogue.tenancy.foreign_key');
    $tenantModel = config('eclipse-catalogue.tenancy.model');
    $currentTenantId = $tenantModel::first()->id;

    ProductData::factory()->create([
        'product_id' => $laptop->id,
        $tenantFK => $currentTenantId,
        'category_id' => $electronics->id,
        'is_active' => true,
    ]);

    ProductData::factory()->create([
        'product_id' => $novel->id,
        $tenantFK => $currentTenantId,
        'category_id' => $books->id,
        'is_active' => true,
    ]);

    Livewire::test(ListProducts::class)
        ->filterTable('category_id', [$electronics->id])
        ->assertCanSeeTableRecords([$laptop])
        ->assertCanNotSeeTableRecords([$novel, $orphanProduct]);
});
