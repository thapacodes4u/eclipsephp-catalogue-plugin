<?php

declare(strict_types=1);

namespace Eclipse\Catalogue\Policies;

use Eclipse\Catalogue\Models\ProductType;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ProductTypePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(AuthUser $user): bool
    {
        return $user->can('view_any_product_type');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(AuthUser $user, ProductType $productType): bool
    {
        return $user->can('view_product_type');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(AuthUser $user): bool
    {
        return $user->can('create_product_type');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(AuthUser $user, ProductType $productType): bool
    {
        return $user->can('update_product_type');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(AuthUser $user, ProductType $productType): bool
    {
        return $user->can('delete_product_type');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(AuthUser $user): bool
    {
        return $user->can('delete_any_product_type');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(AuthUser $user, ProductType $productType): bool
    {
        return $user->can('force_delete_product_type');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(AuthUser $user): bool
    {
        return $user->can('force_delete_any_product_type');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(AuthUser $user, ProductType $productType): bool
    {
        return $user->can('restore_product_type');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(AuthUser $user): bool
    {
        return $user->can('restore_any_product_type');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(AuthUser $user, ProductType $productType): bool
    {
        return $user->can('replicate_product_type');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(AuthUser $user): bool
    {
        return $user->can('reorder_product_type');
    }
}
