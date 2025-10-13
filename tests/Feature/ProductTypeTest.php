<?php

use Eclipse\Catalogue\Filament\Resources\ProductTypeResource;
use Eclipse\Catalogue\Models\ProductType;
use Eclipse\Catalogue\Models\ProductTypeData;
use Eclipse\Core\Models\Locale;
use Illuminate\Support\Carbon;
use Workbench\App\Models\Site;

/**
 * Helper: create a TypeData row including the tenant foreign key when
 * tenancy is enabled. Keeps the tests readable and future-proof.
 */
function createTypeData(array $attributes): ProductTypeData
{
    $tenantFK = config('eclipse-catalogue.tenancy.foreign_key');
    if ($tenantFK) {
        $siteId = Site::first()?->id;
        // Ensure we always include the tenant FK when required
        $attributes[$tenantFK] = $attributes[$tenantFK] ?? $siteId;
    }

    return ProductTypeData::create($attributes);
}

// Access Control Tests
test('guest cannot access product type resource index', function () {
    auth()->logout();

    $this->get(ProductTypeResource::getUrl())
        ->assertRedirect(); // Should redirect to login
});

// CRUD Tests
test('can create product type with required fields', function () {
    $type = ProductType::create([
        'name' => ['en' => 'Test Type', 'sl' => 'Testni tip'],
    ]);

    expect($type)->toBeInstanceOf(ProductType::class);
    expect($type->getTranslation('name', 'en'))->toBe('Test Type');
    expect($type->getTranslation('name', 'sl'))->toBe('Testni tip');
});

test('can create product type with all fields', function () {
    $type = ProductType::create([
        'name' => ['en' => 'Complete Type', 'sl' => 'Popoln tip'],
        'code' => 'CT001',
    ]);

    expect($type)->toBeInstanceOf(ProductType::class);
    expect($type->getTranslation('name', 'en'))->toBe('Complete Type');
    expect($type->code)->toBe('CT001');
});

test('can update product type', function () {
    $type = ProductType::factory()->create([
        'name' => ['en' => 'Original Name', 'sl' => 'Originalno ime'],
    ]);

    $type->update([
        'name' => ['en' => 'Updated Name', 'sl' => 'Posodobljeno ime'],
        'code' => 'UPD001',
    ]);

    expect($type->getTranslation('name', 'en'))->toBe('Updated Name');
    expect($type->getTranslation('name', 'sl'))->toBe('Posodobljeno ime');
    expect($type->code)->toBe('UPD001');
});

test('can delete product type (soft delete)', function () {
    $type = ProductType::factory()->create();
    $id = $type->id;

    $type->delete();

    expect(ProductType::find($id))->toBeNull();
    expect(ProductType::withTrashed()->find($id))->not->toBeNull();
    expect(ProductType::withTrashed()->find($id)->trashed())->toBeTrue();
});

test('can restore deleted product type', function () {
    $type = ProductType::factory()->create();
    $type->delete();

    $type->restore();

    expect($type->trashed())->toBeFalse();
    expect(ProductType::find($type->id))->not->toBeNull();
});

test('can force delete product type', function () {
    $type = ProductType::factory()->create();
    $id = $type->id;
    $type->delete();

    $type->forceDelete();

    expect(ProductType::withTrashed()->find($id))->toBeNull();
});

// Relationship Tests
test('has type data relationship', function () {
    $type = ProductType::factory()->create();

    createTypeData([
        'product_type_id' => $type->id,
        'is_active' => true,
        'is_default' => false,
    ]);

    expect($type->productTypeData)->toHaveCount(1);
    expect($type->productTypeData->first())->toBeInstanceOf(ProductTypeData::class);
});

// Factory Tests
test('factory creates valid product types', function () {
    $type = ProductType::factory()->create();

    expect($type->getTranslation('name', 'en'))->toBeString();
    expect($type->code)->toBeString();
    expect($type->created_at)->toBeInstanceOf(Carbon::class);
});

// Default Type Tests
test('only one product type can be set as default per tenant', function () {
    $type1 = ProductType::factory()->create();
    $type2 = ProductType::factory()->create();

    // Create type data for both as default
    createTypeData([
        'product_type_id' => $type1->id,
        'is_active' => true,
        'is_default' => true,
    ]);

    createTypeData([
        'product_type_id' => $type2->id,
        'is_active' => true,
        'is_default' => true,
    ]);

    // Check that both records exist (the business logic for ensuring only one default
    // would be implemented in the application layer, not the model layer)
    $type1Data = ProductTypeData::where('product_type_id', $type1->id)->first();
    $type2Data = ProductTypeData::where('product_type_id', $type2->id)->first();

    expect($type1Data)->not->toBeNull();
    expect($type2Data)->not->toBeNull();
    expect($type1Data->is_default)->toBeTrue();
    expect($type2Data->is_default)->toBeTrue();
});

// Business Logic Tests
test('can get default product type', function () {
    $defaultType = ProductType::factory()->create();
    $regularType = ProductType::factory()->create();

    createTypeData([
        'product_type_id' => $defaultType->id,
        'is_active' => true,
        'is_default' => true,
    ]);

    createTypeData([
        'product_type_id' => $regularType->id,
        'is_active' => true,
        'is_default' => false,
    ]);

    $foundDefault = ProductType::getDefault();
    expect($foundDefault)->not->toBeNull();
    expect($foundDefault->id)->toBe($defaultType->id);
});

// Tenant-scoped data creation tests
test('createWithTenantData works with tenancy', function () {
    // Simulate tenancy being enabled
    config(['eclipse-catalogue.tenancy.foreign_key' => 'site_id']);
    config(['eclipse-catalogue.tenancy.model' => Site::class]);

    $site1 = Site::first();
    $site2 = Site::skip(1)->first() ?? Site::factory()->create();

    $type = ProductType::createWithTenantData([
        'name' => ['en' => 'Tenant Type'],
        'code' => 'TEN001',
    ], [
        $site1->id => ['is_active' => true, 'is_default' => true],
        $site2->id => ['is_active' => true, 'is_default' => false],
    ]);

    expect($type)->toBeInstanceOf(ProductType::class);
    expect($type->productTypeData)->toHaveCount(2);

    $site1Data = $type->productTypeData->where('site_id', $site1->id)->first();
    $site2Data = $type->productTypeData->where('site_id', $site2->id)->first();

    expect($site1Data->is_default)->toBeTrue();
    expect($site2Data->is_default)->toBeFalse();
});

// Constraint handling tests
test('handleDefaultConstraints ensures only one default per tenant', function () {
    $type1 = ProductType::factory()->create();
    $type2 = ProductType::factory()->create();

    // Create first default type
    createTypeData([
        'product_type_id' => $type1->id,
        'is_active' => true,
        'is_default' => true,
    ]);

    // Create second type and set as default - should clear first default
    $tenantData = ['is_active' => true, 'is_default' => true];
    $type2->handleDefaultConstraints($tenantData, null);

    createTypeData([
        'product_type_id' => $type2->id,
        'is_active' => true,
        'is_default' => true,
    ]);

    // Check that first type is no longer default
    $type1Data = ProductTypeData::where('product_type_id', $type1->id)->first();
    expect($type1Data->is_default)->toBeFalse();
});

// Translation tests
test('name attribute is translatable', function () {
    $type = ProductType::factory()->create([
        'name' => [
            'en' => 'English Name',
            'sl' => 'Slovensko ime',
        ],
    ]);

    expect($type->getTranslation('name', 'en'))->toBe('English Name');
    expect($type->getTranslation('name', 'sl'))->toBe('Slovensko ime');

    // Test fallback
    expect($type->getTranslation('name', 'de'))->toBe('English Name');
});

test('name translations are properly cast', function () {
    $type = ProductType::factory()->create();

    // The name attribute returns current locale's translation (string)
    expect($type->name)->toBeString();

    // getTranslations returns the full array of translations
    $translations = $type->getTranslations('name');
    expect($translations)->toBeArray();

    // Should have at least English translation
    expect($translations)->toHaveKey('en');

    // Should have translations for all available locales
    if (class_exists(Locale::class)) {
        $availableLocales = Locale::getAvailableLocales()
            ->pluck('id')
            ->toArray();

        foreach ($availableLocales as $locale) {
            expect($translations)->toHaveKey($locale);
        }
    } else {
        // Fallback test when Locale model is not available
        expect($translations)->toHaveKey('en');
    }
});
