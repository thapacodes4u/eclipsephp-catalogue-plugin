<?php

declare(strict_types=1);

namespace Eclipse\Catalogue\Policies;

use Eclipse\Catalogue\Models\Property;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class PropertyPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(AuthUser $user): bool
    {
        return $user->can('view_any_property');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(AuthUser $user, Property $property): bool
    {
        return $user->can('view_property');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(AuthUser $user): bool
    {
        return $user->can('create_property');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(AuthUser $user, Property $property): bool
    {
        return $user->can('update_property');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(AuthUser $user, Property $property): bool
    {
        return $user->can('delete_property');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(AuthUser $user): bool
    {
        return $user->can('delete_any_property');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(AuthUser $user, Property $property): bool
    {
        return $user->can('force_delete_property');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(AuthUser $user): bool
    {
        return $user->can('force_delete_any_property');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(AuthUser $user, Property $property): bool
    {
        return $user->can('restore_property');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(AuthUser $user): bool
    {
        return $user->can('restore_any_property');
    }
}
