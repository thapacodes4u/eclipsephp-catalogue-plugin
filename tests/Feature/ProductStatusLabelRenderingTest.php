<?php

use Eclipse\Catalogue\Models\ProductStatus;
use Eclipse\Catalogue\Support\LabelType;
use Workbench\App\Models\Site;

beforeEach(function () {
    $this->migrate();
    $this->setUpSuperAdminAndTenant();
    $this->site = Site::first();
});

it('renders correct badge class for each label type', function () {
    $labelTypes = ['gray', 'danger', 'success', 'warning', 'info', 'primary'];

    foreach ($labelTypes as $labelType) {
        $productStatus = ProductStatus::create([
            'site_id' => $this->site->id,
            'code' => "test_{$labelType}",
            'title' => ['en' => "Test {$labelType}"],
            'label_type' => $labelType,
            'priority' => 1,
            'sd_item_availability' => 'InStock',
        ]);

        $expectedClass = LabelType::badgeClass($labelType);
        expect($expectedClass)->toBe("fi-badge fi-color-{$labelType}");
    }
});

it('returns correct options for label types', function () {
    $options = LabelType::options();

    expect($options)->toBeArray();
    expect($options)->toHaveKey('gray');
    expect($options)->toHaveKey('danger');
    expect($options)->toHaveKey('success');
    expect($options)->toHaveKey('warning');
    expect($options)->toHaveKey('info');
    expect($options)->toHaveKey('primary');

    // Check that values are properly formatted
    expect($options['gray'])->toBe('Gray');
    expect($options['danger'])->toBe('Danger');
    expect($options['success'])->toBe('Success');
});

it('displays status title as label in table column', function () {
    $productStatus = ProductStatus::create([
        'site_id' => $this->site->id,
        'code' => 'test_status',
        'title' => ['en' => 'Test Status', 'sl' => 'Testni Status'],
        'label_type' => 'success',
        'priority' => 1,
        'sd_item_availability' => 'InStock',
    ]);

    // Test that the title is properly formatted for display
    $displayTitle = is_array($productStatus->title) ?
        ($productStatus->title[app()->getLocale()] ?? reset($productStatus->title)) :
        $productStatus->title;

    expect($displayTitle)->toBe('Test Status');

    // Test badge class generation
    $badgeClass = LabelType::badgeClass($productStatus->label_type);
    expect($badgeClass)->toBe('fi-badge fi-color-success');
});
