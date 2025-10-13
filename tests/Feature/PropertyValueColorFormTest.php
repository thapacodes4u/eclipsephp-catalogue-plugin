<?php

use Eclipse\Catalogue\Filament\Resources\PropertyValueResource;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;

beforeEach(function () {
    $this->migrate();
});

it('builds color group schema correctly', function () {
    // Test that color form fields are properly structured
    $resource = new PropertyValueResource;
    $reflection = new ReflectionClass($resource);
    $method = $reflection->getMethod('buildColorGroupSchema');
    $method->setAccessible(true);

    $colorGroupSchema = $method->invoke($resource);

    // Should return one main group component
    expect($colorGroupSchema)->toHaveCount(1);
    expect($colorGroupSchema[0])->toBeInstanceOf(Group::class);

    // Group should contain 4 form components
    $groupSchema = $colorGroupSchema[0]->getDefaultChildComponents();
    expect($groupSchema)->toHaveCount(4);

    // Verify component types: Radio (background type), ColorPicker, Grid (gradient), ViewField (preview)
    expect($groupSchema[0])->toBeInstanceOf(Radio::class);
    expect($groupSchema[1])->toBeInstanceOf(ColorPicker::class);
    expect($groupSchema[2])->toBeInstanceOf(Grid::class);
    expect($groupSchema[3])->toBeInstanceOf(ViewField::class);
});

it('configures color picker component', function () {
    // Test that the color picker is properly configured for solid colors
    $resource = new PropertyValueResource;
    $reflection = new ReflectionClass($resource);
    $method = $reflection->getMethod('buildColorGroupSchema');
    $method->setAccessible(true);

    $colorGroupSchema = $method->invoke($resource);
    $groupSchema = $colorGroupSchema[0]->getDefaultChildComponents();
    $colorPicker = $groupSchema[1];

    expect($colorPicker)->toBeInstanceOf(ColorPicker::class);
    expect($colorPicker->getName())->toBe('color');
});

it('configures gradient grid component', function () {
    // Test that gradient controls are properly structured
    $resource = new PropertyValueResource;
    $reflection = new ReflectionClass($resource);
    $method = $reflection->getMethod('buildColorGroupSchema');
    $method->setAccessible(true);

    $colorGroupSchema = $method->invoke($resource);
    $groupSchema = $colorGroupSchema[0]->getDefaultChildComponents();
    $gradientGrid = $groupSchema[2];

    expect($gradientGrid)->toBeInstanceOf(Grid::class);

    // Should contain color_start, color_end, gradient_direction, gradient_style
    $gridChildren = $gradientGrid->getDefaultChildComponents();
    expect($gridChildren)->toHaveCount(4);
});

it('configures color preview component', function () {
    // Test that the color preview field is properly set up
    $resource = new PropertyValueResource;
    $reflection = new ReflectionClass($resource);
    $method = $reflection->getMethod('buildColorGroupSchema');
    $method->setAccessible(true);

    $colorGroupSchema = $method->invoke($resource);
    $groupSchema = $colorGroupSchema[0]->getDefaultChildComponents();
    $previewField = $groupSchema[3];

    expect($previewField)->toBeInstanceOf(ViewField::class);
    expect($previewField->getName())->toBe('preview');
});
