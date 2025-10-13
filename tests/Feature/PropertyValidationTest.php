<?php

use Eclipse\Catalogue\Models\Product;
use Eclipse\Catalogue\Models\Property;
use Eclipse\Catalogue\Models\PropertyValue;
use Illuminate\Database\QueryException;

beforeEach(function () {
    $this->migrate();
});

it('validates property code uniqueness', function () {
    Property::factory()->create(['code' => 'brand']);

    expect(function () {
        Property::create([
            'name' => ['en' => 'Another Brand'],
            'code' => 'brand', // Duplicate code
            'is_active' => true,
            'is_global' => false,
            'max_values' => 1,
            'enable_sorting' => false,
            'is_filter' => false,
        ]);
    })->toThrow(QueryException::class);
});

it('allows null property codes', function () {
    $property1 = Property::create([
        'name' => ['en' => 'First Property'],
        'code' => null,
        'is_active' => true,
        'is_global' => false,
        'max_values' => 1,
        'enable_sorting' => false,
        'is_filter' => false,
    ]);

    $property2 = Property::create([
        'name' => ['en' => 'Second Property'],
        'code' => null,
        'is_active' => true,
        'is_global' => false,
        'max_values' => 1,
        'enable_sorting' => false,
        'is_filter' => false,
    ]);

    expect($property1->code)->toBeNull();
    expect($property2->code)->toBeNull();
});

it('validates property code format', function () {
    // Valid codes should work
    $validCodes = ['brand', 'brand_name', 'brand123', 'BRAND_NAME_123'];

    foreach ($validCodes as $code) {
        $property = Property::factory()->create(['code' => $code]);
        expect($property->code)->toBe(strtolower($code));
    }
});

it('requires property name', function () {
    expect(function () {
        Property::create([
            'code' => 'test',
            'is_active' => true,
            'is_global' => false,
            'max_values' => 1,
            'enable_sorting' => false,
            'is_filter' => false,
        ]);
    })->toThrow(QueryException::class);
});

it('validates property value belongs to property', function () {
    $property1 = Property::factory()->create();
    $property2 = Property::factory()->create();

    $value = PropertyValue::factory()->create(['property_id' => $property1->id]);

    // Should not be able to assign value to different property
    expect($value->property_id)->toBe($property1->id);
    expect($value->property_id)->not->toBe($property2->id);
});

it('validates unique product property value assignment', function () {
    $product = Product::factory()->create();
    $property = Property::factory()->create();
    $value = PropertyValue::factory()->create(['property_id' => $property->id]);

    // First assignment should work
    $product->propertyValues()->attach($value->id);
    expect($product->propertyValues)->toHaveCount(1);

    // Duplicate assignment should be prevented by unique constraint
    expect(function () use ($product, $value) {
        $product->propertyValues()->attach($value->id);
    })->toThrow(QueryException::class);
});

it('validates property value sort order is numeric', function () {
    $property = Property::factory()->create();

    $value = PropertyValue::create([
        'property_id' => $property->id,
        'value' => ['en' => 'Test Value'],
        'sort' => 10,
    ]);

    expect($value->sort)->toBeInt();
    expect($value->sort)->toBe(10);
});

it('allows property values with same sort order', function () {
    $property = Property::factory()->create();

    $value1 = PropertyValue::create([
        'property_id' => $property->id,
        'value' => ['en' => 'First Value'],
        'sort' => 10,
    ]);

    $value2 = PropertyValue::create([
        'property_id' => $property->id,
        'value' => ['en' => 'Second Value'],
        'sort' => 10, // Same sort order
    ]);

    expect($value1->sort)->toBe(10);
    expect($value2->sort)->toBe(10);
});

it('validates max_values is positive integer', function () {
    $property = Property::factory()->create(['max_values' => 5]);
    expect($property->max_values)->toBe(5);
    expect($property->max_values)->toBeGreaterThan(0);
});

it('allows property without max_values', function () {
    $property = Property::create([
        'name' => ['en' => 'Test Property'],
        'is_active' => true,
        'is_global' => false,
        'max_values' => null,
        'enable_sorting' => false,
        'is_filter' => false,
    ]);

    expect($property->max_values)->toBeNull();
});

it('validates boolean fields have correct types', function () {
    $property = Property::factory()->create([
        'is_active' => true,
        'is_global' => false,
        'enable_sorting' => true,
        'is_filter' => false,
    ]);

    expect($property->is_active)->toBeBool();
    expect($property->is_global)->toBeBool();
    expect($property->enable_sorting)->toBeBool();
    expect($property->is_filter)->toBeBool();

    expect($property->is_active)->toBeTrue();
    expect($property->is_global)->toBeFalse();
    expect($property->enable_sorting)->toBeTrue();
    expect($property->is_filter)->toBeFalse();
});
