<?php

use Eclipse\Catalogue\Filament\Resources\CategoryResource;
use Eclipse\Catalogue\Filament\Resources\CategoryResource\Pages\CreateCategory;
use Eclipse\Catalogue\Filament\Resources\CategoryResource\Pages\EditCategory;
use Eclipse\Catalogue\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->setUpSuperAdmin();
});

it('can render category index page', function (): void {
    $this->get(CategoryResource::getUrl('index'))
        ->assertSuccessful();
});

it('can create category', function (): void {
    $factoryData = Category::factory()->definition();

    Livewire::test(CreateCategory::class)
        ->fillForm([
            'name' => $factoryData['name']['en'],
            'sef_key' => $factoryData['sef_key']['en'],
            'short_desc' => $factoryData['short_desc']['en'],
            'description' => $factoryData['description']['en'],
            'is_active' => $factoryData['is_active'],
            'recursive_browsing' => $factoryData['recursive_browsing'],
            'code' => $factoryData['code'],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $createdCategory = Category::latest()->first();
    expect($createdCategory->name)->not()->toBeNull();
});

it('can edit category', function (): void {
    $category = Category::factory()->create();

    Livewire::test(EditCategory::class, [
        'record' => $category->getRouteKey(),
    ])
        ->fillForm([
            'name' => 'Updated Name',
            'sef_key' => 'updated-name',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($category->fresh())
        ->name->toBe('Updated Name')
        ->sef_key->toBe('updated-name');
});

it('can delete category', function (): void {
    $category = Category::factory()->create();

    Livewire::test(EditCategory::class, [
        'record' => $category->getRouteKey(),
    ])
        ->callAction('delete');

    expect($category->fresh()->trashed())->toBeTrue();
});

it('validates unique sef_key within same site', function (): void {
    $existingCategory = Category::factory()->create([
        'sef_key' => 'electronics',
    ]);

    Livewire::test(CreateCategory::class)
        ->fillForm([
            'name' => 'New Category',
            'sef_key' => 'electronics',
        ])
        ->call('create')
        ->assertHasFormErrors(['sef_key']);
});

it('prevents circular parent relationship', function (): void {
    $parent = Category::factory()->create(['name' => 'Parent']);
    $child = Category::factory()->create([
        'name' => 'Child',
        'parent_id' => $parent->id,
    ]);

    Livewire::test(EditCategory::class, [
        'record' => $parent->getRouteKey(),
    ])
        ->fillForm([
            'parent_id' => $child->id,
        ])
        ->call('save')
        ->assertHasFormErrors(['parent_id']);
});
