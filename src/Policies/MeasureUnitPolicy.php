<?php

declare(strict_types=1);

namespace Eclipse\Catalogue\Policies;

use Eclipse\Catalogue\Models\MeasureUnit;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class MeasureUnitPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(AuthUser $user): bool
    {
        return $user->can('view_any_measure_unit');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(AuthUser $user, MeasureUnit $measureUnit): bool
    {
        return $user->can('view_measure_unit');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(AuthUser $user): bool
    {
        return $user->can('create_measure_unit');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(AuthUser $user, MeasureUnit $measureUnit): bool
    {
        return $user->can('update_measure_unit');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(AuthUser $user, MeasureUnit $measureUnit): bool
    {
        // Prevent deletion of default unit
        if ($measureUnit->is_default) {
            return false;
        }

        return $user->can('delete_measure_unit');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(AuthUser $user): bool
    {
        return $user->can('delete_any_measure_unit');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(AuthUser $user, MeasureUnit $measureUnit): bool
    {
        // Prevent force deletion of default unit
        if ($measureUnit->is_default) {
            return false;
        }

        return $user->can('force_delete_measure_unit');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(AuthUser $user): bool
    {
        return $user->can('force_delete_any_measure_unit');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(AuthUser $user, MeasureUnit $measureUnit): bool
    {
        return $user->can('restore_measure_unit');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(AuthUser $user): bool
    {
        return $user->can('restore_any_measure_unit');
    }
}
