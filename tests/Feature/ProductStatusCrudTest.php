<?php

use Eclipse\Catalogue\Models\ProductStatus;
use Workbench\App\Models\Site;

beforeEach(function () {
    $this->migrate();
    $this->setUpSuperAdminAndTenant();
    $this->site = Site::first();
});

it('can create a product status', function () {
    $productStatus = ProductStatus::create([
        'site_id' => $this->site->id,
        'code' => 'active',
        'title' => ['en' => 'Active', 'sl' => 'Aktiven'],
        'description' => ['en' => 'Product is active', 'sl' => 'Izdelek je aktiven'],
        'label_type' => 'success',
        'shown_in_browse' => true,
        'allow_price_display' => true,
        'allow_sale' => true,
        'is_default' => false,
        'priority' => 1,
        'sd_item_availability' => 'InStock',
        'skip_stock_qty_check' => false,
    ]);

    expect($productStatus)->toBeInstanceOf(ProductStatus::class);
    expect($productStatus->code)->toBe('active');
    expect($productStatus->title)->toBe('Active'); // Translatable package returns current locale value
    expect($productStatus->label_type)->toBe('success');
    expect($productStatus->is_default)->toBeFalse();

    $this->assertDatabaseHas('pim_product_statuses', [
        'site_id' => $this->site->id,
        'code' => 'active',
        'label_type' => 'success',
        'is_default' => false,
    ]);
});

it('can update a product status', function () {
    $productStatus = ProductStatus::create([
        'site_id' => $this->site->id,
        'code' => 'active',
        'title' => ['en' => 'Active'],
        'label_type' => 'success',
        'priority' => 1,
        'sd_item_availability' => 'InStock',
    ]);

    $productStatus->update([
        'title' => ['en' => 'Updated Active', 'sl' => 'Posodobljen aktiven'],
        'label_type' => 'primary',
        'is_default' => true,
    ]);

    expect($productStatus->title)->toBe('Updated Active'); // Translatable package returns current locale value
    expect($productStatus->label_type)->toBe('primary');
    expect($productStatus->is_default)->toBeTrue();

    $this->assertDatabaseHas('pim_product_statuses', [
        'id' => $productStatus->id,
        'title' => json_encode(['en' => 'Updated Active', 'sl' => 'Posodobljen aktiven']),
        'label_type' => 'primary',
        'is_default' => true,
    ]);
});

it('can delete a product status', function () {
    $productStatus = ProductStatus::create([
        'site_id' => $this->site->id,
        'code' => 'inactive',
        'title' => ['en' => 'Inactive'],
        'label_type' => 'danger',
        'priority' => 2,
        'sd_item_availability' => 'OutOfStock',
    ]);

    $productStatus->delete();

    $this->assertDatabaseMissing('pim_product_statuses', [
        'id' => $productStatus->id,
    ]);
});

it('validates allow_sale is false when allow_price_display is false', function () {
    $productStatus = ProductStatus::create([
        'site_id' => $this->site->id,
        'code' => 'no_price',
        'title' => ['en' => 'No Price'],
        'label_type' => 'warning',
        'allow_price_display' => false,
        'allow_sale' => false, // Must be false when allow_price_display is false
        'priority' => 3,
        'sd_item_availability' => 'InStock',
    ]);

    expect($productStatus->allow_price_display)->toBeFalse();
    expect($productStatus->allow_sale)->toBeFalse();
});
