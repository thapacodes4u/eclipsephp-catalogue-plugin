<?php

use Eclipse\Catalogue\Enums\BackgroundType;
use Eclipse\Catalogue\Enums\GradientDirection;
use Eclipse\Catalogue\Enums\GradientStyle;
use Eclipse\Catalogue\Enums\PropertyType;
use Eclipse\Catalogue\Models\Property;
use Eclipse\Catalogue\Models\PropertyValue;
use Eclipse\Catalogue\Values\Background;

beforeEach(function () {
    $this->migrate();
});

it('validates property type enum constraints', function () {
    // Test valid property types are accepted
    $property1 = Property::factory()->create(['type' => PropertyType::LIST->value]);
    expect($property1->type)->toBe(PropertyType::LIST->value);

    $property2 = Property::factory()->create(['type' => PropertyType::COLOR->value]);
    expect($property2->type)->toBe(PropertyType::COLOR->value);

    $property3 = Property::factory()->create(['type' => PropertyType::CUSTOM->value]);
    expect($property3->type)->toBe(PropertyType::CUSTOM->value);

    // Test invalid types throw exception
    expect(function () {
        Property::factory()->create(['type' => 'invalid']);
    })->toThrow(InvalidArgumentException::class, 'Invalid type');
});

it('validates background type enum constraints', function () {
    $background = Background::fromArray(['type' => BackgroundType::NONE->value]);
    expect($background->type)->toBe(BackgroundType::NONE->value);

    $background = Background::fromArray(['type' => BackgroundType::SOLID->value]);
    expect($background->type)->toBe(BackgroundType::SOLID->value);

    $background = Background::fromArray(['type' => BackgroundType::GRADIENT->value]);
    expect($background->type)->toBe(BackgroundType::GRADIENT->value);

    $background = Background::fromArray(['type' => BackgroundType::MULTICOLOR->value]);
    expect($background->type)->toBe(BackgroundType::MULTICOLOR->value);

    $background = Background::fromArray(['type' => 'invalid']);
    expect($background->type)->toBe('invalid');
});

it('validates gradient direction and style enums', function () {
    $background = Background::gradient('#ff0000', '#0000ff', GradientDirection::TOP->value, GradientStyle::SHARP->value);
    expect($background->gradient_direction)->toBe(GradientDirection::TOP->value);
    expect($background->gradient_style)->toBe(GradientStyle::SHARP->value);

    $background = Background::gradient('#ff0000', '#0000ff', GradientDirection::BOTTOM->value, GradientStyle::SOFT->value);
    expect($background->gradient_direction)->toBe(GradientDirection::BOTTOM->value);
    expect($background->gradient_style)->toBe(GradientStyle::SOFT->value);
});

it('saves and retrieves none background type', function () {
    $property = Property::factory()->create(['type' => PropertyType::COLOR->value]);
    $value = PropertyValue::factory()->create([
        'property_id' => $property->id,
        'color' => Background::none(),
    ]);

    $retrieved = PropertyValue::find($value->id);
    expect($retrieved->color)->toBeInstanceOf(Background::class);
    expect($retrieved->color->type)->toBe(BackgroundType::NONE->value);
    expect($retrieved->color->isSolid())->toBeFalse();
    expect($retrieved->color->isGradient())->toBeFalse();
    expect($retrieved->color->isMulticolor())->toBeFalse();
});

it('saves and retrieves solid color background', function () {
    $property = Property::factory()->create(['type' => PropertyType::COLOR->value]);
    $solidBg = Background::solid('#ff0000');

    $value = PropertyValue::factory()->create([
        'property_id' => $property->id,
        'color' => $solidBg,
    ]);

    // Verify database persistence and retrieval
    $retrieved = PropertyValue::find($value->id);
    expect($retrieved->color)->toBeInstanceOf(Background::class);
    expect($retrieved->color->type)->toBe(BackgroundType::SOLID->value);
    expect($retrieved->color->color)->toBe('#ff0000');
    expect($retrieved->color->isSolid())->toBeTrue();

    // Verify CSS output
    expect($retrieved->getColor())->toBe('background-color: #ff0000;');
});

it('saves and retrieves gradient background', function () {
    $property = Property::factory()->create(['type' => PropertyType::COLOR->value]);
    $gradientBg = Background::gradient('#ff0000', '#0000ff', 'bottom', GradientStyle::SHARP->value);

    $value = PropertyValue::factory()->create([
        'property_id' => $property->id,
        'color' => $gradientBg,
    ]);

    $retrieved = PropertyValue::find($value->id);
    expect($retrieved->color)->toBeInstanceOf(Background::class);
    expect($retrieved->color->type)->toBe(BackgroundType::GRADIENT->value);
    expect($retrieved->color->color_start)->toBe('#ff0000');
    expect($retrieved->color->color_end)->toBe('#0000ff');
    expect($retrieved->color->gradient_direction)->toBe('bottom');
    expect($retrieved->color->gradient_style)->toBe(GradientStyle::SHARP->value);
    expect($retrieved->color->isGradient())->toBeTrue();
    expect($retrieved->getColor())->toContain('background-image: linear-gradient(to bottom, #ff0000, #ff0000 50%, #0000ff 50%, #0000ff)');
});

it('saves and retrieves soft gradient background', function () {
    $property = Property::factory()->create(['type' => PropertyType::COLOR->value]);
    $gradientBg = Background::gradient('#ff0000', '#0000ff', 'right', GradientStyle::SOFT->value);

    $value = PropertyValue::factory()->create([
        'property_id' => $property->id,
        'color' => $gradientBg,
    ]);

    $retrieved = PropertyValue::find($value->id);
    expect($retrieved->color->gradient_style)->toBe(GradientStyle::SOFT->value);
    expect($retrieved->getColor())->toContain('background-image: linear-gradient(to right, #ff0000 0%, #0000ff 100%)');
});

it('saves and retrieves multicolor background', function () {
    $property = Property::factory()->create(['type' => PropertyType::COLOR->value]);
    $multicolorBg = Background::multicolor();

    $value = PropertyValue::factory()->create([
        'property_id' => $property->id,
        'color' => $multicolorBg,
    ]);

    $retrieved = PropertyValue::find($value->id);
    expect($retrieved->color)->toBeInstanceOf(Background::class);
    expect($retrieved->color->type)->toBe(BackgroundType::MULTICOLOR->value);
    expect($retrieved->color->isMulticolor())->toBeTrue();
    expect($retrieved->getColor())->toBe('');
});

it('serializes background to JSON correctly', function () {
    $bg = Background::solid('#ff0000');
    $serialized = json_encode($bg->toArray());

    expect(json_decode($serialized, true))->toBe([
        'type' => BackgroundType::SOLID->value,
        'color' => '#ff0000',
        'color_start' => null,
        'color_end' => null,
        'gradient_direction' => null,
        'gradient_style' => null,
    ]);
});

it('renders solid color CSS correctly', function () {
    $bg = Background::solid('#ff0000');

    // Test both __toString() and toCss() methods
    expect((string) $bg)->toBe('background-color: #ff0000;');
    expect($bg->toCss())->toBe('background-color: #ff0000;');
});

it('renders sharp gradient CSS correctly', function () {
    $bg = Background::gradient('#ff0000', '#0000ff', 'bottom', GradientStyle::SHARP->value);
    $css = (string) $bg;

    // Verify sharp gradient format with 50% color stops
    expect($css)->toContain('background-image: linear-gradient');
    expect($css)->toContain('to bottom');
    expect($css)->toContain('#ff0000');
    expect($css)->toContain('#0000ff');
    expect($css)->toContain('50%');
});

it('renders soft gradient CSS correctly', function () {
    $bg = Background::gradient('#ff0000', '#0000ff', 'right', GradientStyle::SOFT->value);
    $css = (string) $bg;

    expect($css)->toContain('background-image: linear-gradient(to right, #ff0000 0%, #0000ff 100%)');
    expect($css)->toContain('linear-gradient');
    expect($css)->toContain('0%');
    expect($css)->toContain('100%');
});

it('renders gradient with different directions correctly', function () {
    $directions = ['top', 'bottom', 'left', 'right'];

    foreach ($directions as $direction) {
        $bg = Background::gradient('#ff0000', '#0000ff', $direction, GradientStyle::SHARP->value);
        $css = (string) $bg;

        expect($css)->toContain("to {$direction}");
    }
});

it('renders empty CSS for none type', function () {
    $bg = Background::none();
    expect((string) $bg)->toBe('');
    expect($bg->toCss())->toBe('');
});

it('renders empty CSS for multicolor type', function () {
    $bg = Background::multicolor();
    expect((string) $bg)->toBe('');
    expect($bg->toCss())->toBe('');
});

it('renders empty CSS for solid color without color value', function () {
    $bg = Background::solid('');
    expect((string) $bg)->toBe('');
    expect($bg->toCss())->toBe('');
});

it('renders gradient with default values when parameters are missing', function () {
    $bg = new Background;
    $bg->type = BackgroundType::GRADIENT->value;
    $bg->color_start = '#ff0000';
    $bg->color_end = '#0000ff';

    $css = (string) $bg;
    expect($css)->toContain('to bottom');
    expect($css)->toContain('background-image: linear-gradient');
    expect($css)->toContain('#ff0000');
    expect($css)->toContain('#0000ff');
    expect($css)->toContain('50%');
});

it('getColor() returns CSS from PropertyValue model', function () {
    $property = Property::factory()->create(['type' => PropertyType::COLOR->value]);
    $solidBg = Background::solid('#00ff00');

    $value = PropertyValue::factory()->create([
        'property_id' => $property->id,
        'color' => $solidBg,
    ]);

    expect($value->getColor())->toBe('background-color: #00ff00;');
});

it('getColor() returns empty string when no color is set', function () {
    $property = Property::factory()->create(['type' => PropertyType::COLOR->value]);
    $value = PropertyValue::factory()->create([
        'property_id' => $property->id,
        'color' => null,
    ]);

    expect($value->getColor())->toBe('');
});

it('hasRenderableCss() returns correct boolean values', function () {
    // Test valid backgrounds return true
    $solid = Background::solid('#ff0000');
    expect($solid->hasRenderableCss())->toBeTrue();

    $gradient = Background::gradient('#ff0000', '#0000ff');
    expect($gradient->hasRenderableCss())->toBeTrue();

    $multicolor = Background::multicolor();
    expect($multicolor->hasRenderableCss())->toBeTrue();

    // Test invalid/empty backgrounds return false
    $solidEmpty = Background::solid('');
    expect($solidEmpty->hasRenderableCss())->toBeFalse();

    $gradientMissingStart = Background::gradient('', '#0000ff');
    expect($gradientMissingStart->hasRenderableCss())->toBeFalse();

    $gradientMissingEnd = Background::gradient('#ff0000', '');
    expect($gradientMissingEnd->hasRenderableCss())->toBeFalse();

    $none = Background::none();
    expect($none->hasRenderableCss())->toBeFalse();
});

it('handles existing property values with null color column', function () {
    // Test backward compatibility for data created before color column existed
    $property = Property::factory()->create(['type' => PropertyType::LIST->value]);

    $value = PropertyValue::create([
        'property_id' => $property->id,
        'value' => ['en' => 'Test Value'],
        'sort' => 10,
    ]);

    $value->color = null;
    $value->save();

    // Verify existing data still works correctly
    $retrieved = PropertyValue::find($value->id);
    expect($retrieved)->toBeInstanceOf(PropertyValue::class);
    expect($retrieved->getTranslation('value', 'en'))->toBe('Test Value');
    expect($retrieved->getColor())->toBe('');
    expect($retrieved->color)->toBeInstanceOf(Background::class);
});

it('fromArray handles null data correctly', function () {
    $bg = Background::fromArray(null);
    expect($bg->type)->toBe(BackgroundType::NONE->value);
    expect($bg->color)->toBeNull();
    expect($bg->color_start)->toBeNull();
    expect($bg->color_end)->toBeNull();
    expect($bg->gradient_direction)->toBeNull();
    expect($bg->gradient_style)->toBeNull();
});

it('fromArray handles empty array correctly', function () {
    $bg = Background::fromArray([]);
    expect($bg->type)->toBe(BackgroundType::NONE->value);
    expect($bg->color)->toBeNull();
    expect($bg->color_start)->toBeNull();
    expect($bg->color_end)->toBeNull();
    expect($bg->gradient_direction)->toBeNull();
    expect($bg->gradient_style)->toBeNull();
});

it('fromArray handles partial data correctly', function () {
    $data = [
        'type' => BackgroundType::SOLID->value,
        'color' => '#ff0000',
    ];

    $bg = Background::fromArray($data);
    expect($bg->type)->toBe(BackgroundType::SOLID->value);
    expect($bg->color)->toBe('#ff0000');
    expect($bg->color_start)->toBeNull();
    expect($bg->color_end)->toBeNull();
    expect($bg->gradient_direction)->toBeNull();
    expect($bg->gradient_style)->toBeNull();
});

it('property value attributesToArray handles color serialization', function () {
    $property = Property::factory()->create(['type' => PropertyType::COLOR->value]);
    $solidBg = Background::solid('#ff0000');

    $value = PropertyValue::factory()->create([
        'property_id' => $property->id,
        'color' => $solidBg,
    ]);

    $attributes = $value->attributesToArray();
    expect($attributes['color'])->toBeArray();
    expect($attributes['color']['type'])->toBe(BackgroundType::SOLID->value);
    expect($attributes['color']['color'])->toBe('#ff0000');
});

it('property value attributesToArray handles null color correctly', function () {
    $property = Property::factory()->create(['type' => PropertyType::LIST->value]);

    $value = PropertyValue::factory()->create([
        'property_id' => $property->id,
        'color' => null,
    ]);

    $attributes = $value->attributesToArray();
    expect($attributes['color'])->toBeArray();
    expect($attributes['color']['type'])->toBe(BackgroundType::NONE->value);
    expect($attributes['color']['color'])->toBeNull();
});
