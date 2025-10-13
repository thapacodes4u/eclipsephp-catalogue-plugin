<?php

use Eclipse\Catalogue\Models\ProductStatus;
use Illuminate\Database\QueryException;
use Workbench\App\Models\Site;

beforeEach(function () {
    $this->migrate();
    $this->setUpSuperAdminAndTenant();
    $this->site = Site::first();
});

it('enforces unique code per site', function () {
    // Create first status
    ProductStatus::create([
        'site_id' => $this->site->id,
        'code' => 'active',
        'title' => ['en' => 'Active'],
        'label_type' => 'success',
        'priority' => 1,
        'sd_item_availability' => 'InStock',
    ]);

    // Try to create second status with same code for same site
    expect(function () {
        ProductStatus::create([
            'site_id' => $this->site->id,
            'code' => 'active', // Same code
            'title' => ['en' => 'Another Active'],
            'label_type' => 'primary',
            'priority' => 2,
            'sd_item_availability' => 'InStock',
        ]);
    })->toThrow(QueryException::class);
});

it('allows same code for different sites', function () {
    // Create second site
    $secondSite = Site::factory()->create();

    // Create status for first site
    $firstStatus = ProductStatus::create([
        'site_id' => $this->site->id,
        'code' => 'active',
        'title' => ['en' => 'Active'],
        'label_type' => 'success',
        'priority' => 1,
        'sd_item_availability' => 'InStock',
    ]);

    // Create status for second site with same code
    $secondStatus = ProductStatus::create([
        'site_id' => $secondSite->id,
        'code' => 'active', // Same code, different site
        'title' => ['en' => 'Active for Second Site'],
        'label_type' => 'primary',
        'priority' => 1,
        'sd_item_availability' => 'InStock',
    ]);

    expect($firstStatus->code)->toBe('active');
    expect($secondStatus->code)->toBe('active');
    expect($firstStatus->site_id)->not->toBe($secondStatus->site_id);
});
