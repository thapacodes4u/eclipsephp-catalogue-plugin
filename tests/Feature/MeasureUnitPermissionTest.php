<?php

use Eclipse\Catalogue\Filament\Resources\MeasureUnitResource;
use Eclipse\Catalogue\Filament\Resources\MeasureUnitResource\Pages\ListMeasureUnits;
use Eclipse\Catalogue\Models\MeasureUnit;
use Eclipse\Catalogue\Policies\MeasureUnitPolicy;
use Workbench\App\Models\User;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->migrate();
});

it('policy prevents deletion of default unit regardless of user permissions', function () {
    $user = User::factory()->create();

    $defaultUnit = MeasureUnit::create([
        'name' => 'Kilogram',
        'is_default' => true,
    ]);

    $policy = new MeasureUnitPolicy;
    $canDelete = $policy->delete($user, $defaultUnit);

    expect($canDelete)->toBeFalse();
});

it('policy prevents force deletion of default unit regardless of user permissions', function () {
    $user = User::factory()->create();

    $defaultUnit = MeasureUnit::create([
        'name' => 'Kilogram',
        'is_default' => true,
    ]);

    $policy = new MeasureUnitPolicy;
    $canForceDelete = $policy->forceDelete($user, $defaultUnit);

    expect($canForceDelete)->toBeFalse();
});

test('unauthorized access can be prevented', function () {
    // Create regular user with no permissions
    $this->setUpCommonUser();

    // Create test measure unit
    $measureUnit = MeasureUnit::create([
        'name' => 'Test Unit',
        'is_default' => false,
    ]);

    // View table
    $this->get(MeasureUnitResource::getUrl())
        ->assertForbidden();

    // Add direct permission to view the table, since otherwise any other action below is not available even for testing
    $this->user->givePermissionTo('view_any_measure_unit');

    // Create measure unit
    livewire(ListMeasureUnits::class)
        ->assertActionDisabled('create');

    // Edit measure unit
    livewire(ListMeasureUnits::class)
        ->assertCanSeeTableRecords([$measureUnit])
        ->assertTableActionDisabled('edit', $measureUnit);

    // Delete measure unit
    livewire(ListMeasureUnits::class)
        ->assertTableActionDisabled('delete', $measureUnit);

    // Restore and force delete
    $measureUnit->delete();
    $this->assertSoftDeleted($measureUnit);

    livewire(ListMeasureUnits::class)
        ->filterTable('trashed')
        ->assertTableActionExists('restore')
        ->assertTableActionExists('forceDelete')
        ->assertTableActionDisabled('restore', $measureUnit)
        ->assertTableActionDisabled('forceDelete', $measureUnit);
});
