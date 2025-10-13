<?php

declare(strict_types=1);

namespace Eclipse\Catalogue\Policies;

use Eclipse\Catalogue\Models\TaxClass;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class TaxClassPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(AuthUser $user): bool
    {
        return $user->can('view_any_tax_class');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(AuthUser $user, TaxClass $taxClass): bool
    {
        return $user->can('view_tax_class');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(AuthUser $user): bool
    {
        return $user->can('create_tax_class');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(AuthUser $user, TaxClass $taxClass): bool
    {
        return $user->can('update_tax_class');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(AuthUser $user, TaxClass $taxClass): bool
    {
        // Prevent deletion of default class
        if ($taxClass->is_default) {
            return false;
        }

        return $user->can('delete_tax_class');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(AuthUser $user): bool
    {
        return $user->can('delete_any_tax_class');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(AuthUser $user, TaxClass $taxClass): bool
    {
        // Prevent force deletion of default class
        if ($taxClass->is_default) {
            return false;
        }

        return $user->can('force_delete_tax_class');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(AuthUser $user): bool
    {
        return $user->can('force_delete_any_tax_class');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(AuthUser $user, TaxClass $taxClass): bool
    {
        return $user->can('restore_tax_class');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(AuthUser $user): bool
    {
        return $user->can('restore_any_tax_class');
    }
}
