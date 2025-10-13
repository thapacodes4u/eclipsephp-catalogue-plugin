<?php

declare(strict_types=1);

namespace Eclipse\Catalogue\Policies;

use Eclipse\Catalogue\Models\ProductStatus;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ProductStatusPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(AuthUser $user): bool
    {
        return $user->can('view_any_product_status');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(AuthUser $user, ProductStatus $productStatus): bool
    {
        return $user->can('view_product_status');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(AuthUser $user): bool
    {
        return $user->can('create_product_status');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(AuthUser $user, ProductStatus $productStatus): bool
    {
        return $user->can('update_product_status');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(AuthUser $user, ProductStatus $productStatus): bool
    {
        // Prevent deletion of default status
        if ($productStatus->is_default) {
            return false;
        }

        return $user->can('delete_product_status');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(AuthUser $user): bool
    {
        return $user->can('delete_any_product_status');
    }
}
