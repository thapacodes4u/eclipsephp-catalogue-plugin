<?php

use Eclipse\Catalogue\Models\Group;
use Illuminate\Database\QueryException;

beforeEach(function () {
    $this->migrate();
});

it('allows same code across different sites', function () {
    // Create groups with same code on different sites
    $group1 = Group::create([
        'site_id' => 1,
        'code' => 'same-code',
        'name' => 'Group 1',
        'is_active' => true,
    ]);

    $group2 = Group::create([
        'site_id' => 2,
        'code' => 'same-code',
        'name' => 'Group 2',
        'is_active' => true,
    ]);

    expect($group1->code)->toBe('same-code');
    expect($group2->code)->toBe('same-code');
    expect($group1->site_id)->toBe(1);
    expect($group2->site_id)->toBe(2);

    $this->assertDatabaseHas('pim_group', [
        'id' => $group1->id,
        'site_id' => 1,
        'code' => 'same-code',
    ]);

    $this->assertDatabaseHas('pim_group', [
        'id' => $group2->id,
        'site_id' => 2,
        'code' => 'same-code',
    ]);
});

it('prevents duplicate code within the same site', function () {
    // Create first group
    Group::create([
        'site_id' => 1,
        'code' => 'unique-code',
        'name' => 'First Group',
        'is_active' => true,
    ]);

    // Attempt to create second group with same code on same site
    expect(fn () => Group::create([
        'site_id' => 1,
        'code' => 'unique-code',
        'name' => 'Second Group',
        'is_active' => true,
    ]))->toThrow(QueryException::class);
});

it('allows updating group with same code on same site', function () {
    $group = Group::create([
        'site_id' => 1,
        'code' => 'original-code',
        'name' => 'Original Name',
        'is_active' => true,
    ]);

    // Update the same group - should work
    $group->update([
        'name' => 'Updated Name',
        'is_active' => false,
    ]);

    expect($group->code)->toBe('original-code');
    expect($group->name)->toBe('Updated Name');
    expect($group->is_active)->toBeFalse();

    $this->assertDatabaseHas('pim_group', [
        'id' => $group->id,
        'code' => 'original-code',
        'name' => 'Updated Name',
        'is_active' => false,
    ]);
});
