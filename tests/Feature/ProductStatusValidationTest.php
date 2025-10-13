<?php

use Eclipse\Catalogue\Models\ProductStatus;
use Workbench\App\Models\Site;

beforeEach(function () {
    $this->migrate();
    $this->setUpSuperAdminAndTenant();
    $this->site = Site::first();
});

it('enforces allow_sale false when allow_price_display is false', function () {
    // Create status with allow_price_display false
    $productStatus = ProductStatus::create([
        'site_id' => $this->site->id,
        'code' => 'no_price',
        'title' => ['en' => 'No Price Display'],
        'label_type' => 'warning',
        'allow_price_display' => false,
        'allow_sale' => true, // This should be automatically set to false
        'priority' => 1,
        'sd_item_availability' => 'InStock',
    ]);

    // Refresh from database to get actual values
    $productStatus->refresh();

    // allow_sale should be false when allow_price_display is false
    expect($productStatus->allow_price_display)->toBeFalse();
    expect($productStatus->allow_sale)->toBeFalse();
});

it('allows allow_sale true when allow_price_display is true', function () {
    $productStatus = ProductStatus::create([
        'site_id' => $this->site->id,
        'code' => 'with_price',
        'title' => ['en' => 'With Price Display'],
        'label_type' => 'success',
        'allow_price_display' => true,
        'allow_sale' => true,
        'priority' => 1,
        'sd_item_availability' => 'InStock',
    ]);

    // Refresh from database to get actual values
    $productStatus->refresh();

    expect($productStatus->allow_price_display)->toBeTrue();
    expect($productStatus->allow_sale)->toBeTrue();
});

it('validates required fields', function () {
    expect(function () {
        ProductStatus::create([
            'site_id' => $this->site->id,
            // Missing required fields: code, title, label_type, priority, sd_item_availability
        ]);
    })->toThrow(Exception::class);
});

it('validates code length limit', function () {
    expect(function () {
        ProductStatus::create([
            'site_id' => $this->site->id,
            'code' => str_repeat('a', 21), // 21 characters, limit is 20
            'title' => ['en' => 'Too Long Code'],
            'label_type' => 'success',
            'priority' => 1,
            'sd_item_availability' => 'InStock',
        ]);
    })->toThrow(Exception::class);
});
