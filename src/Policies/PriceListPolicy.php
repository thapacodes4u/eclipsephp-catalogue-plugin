<?php

declare(strict_types=1);

namespace Eclipse\Catalogue\Policies;

use Eclipse\Catalogue\Models\PriceList;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class PriceListPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(AuthUser $user): bool
    {
        return $user->can('view_any_price_list');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(AuthUser $user, PriceList $priceList): bool
    {
        return $user->can('view_price_list');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(AuthUser $user): bool
    {
        return $user->can('create_price_list');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(AuthUser $user, PriceList $priceList): bool
    {
        return $user->can('update_price_list');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(AuthUser $user, PriceList $priceList): bool
    {
        // Prevent deletion of default price lists
        if ($priceList->is_default || $priceList->is_default_purchase) {
            return false;
        }

        return $user->can('delete_price_list');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(AuthUser $user): bool
    {
        return $user->can('delete_any_price_list');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(AuthUser $user, PriceList $priceList): bool
    {
        return $user->can('restore_price_list');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(AuthUser $user): bool
    {
        return $user->can('restore_any_price_list');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(AuthUser $user, PriceList $priceList): bool
    {
        // Prevent force deletion of default price lists
        if ($priceList->is_default || $priceList->is_default_purchase) {
            return false;
        }

        return $user->can('force_delete_price_list');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(AuthUser $user): bool
    {
        return $user->can('force_delete_any_price_list');
    }
}
