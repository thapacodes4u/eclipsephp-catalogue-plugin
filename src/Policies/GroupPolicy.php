<?php

declare(strict_types=1);

namespace Eclipse\Catalogue\Policies;

use Eclipse\Catalogue\Models\Group;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class GroupPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $user): bool
    {
        return $user->can('view_any_group');
    }

    public function view(AuthUser $user, Group $group): bool
    {
        return $user->can('view_group');
    }

    public function create(AuthUser $user): bool
    {
        return $user->can('create_group');
    }

    public function update(AuthUser $user, Group $group): bool
    {
        return $user->can('update_group');
    }

    public function delete(AuthUser $user, Group $group): bool
    {
        return $user->can('delete_group');
    }

    public function deleteAny(AuthUser $user): bool
    {
        return $user->can('delete_any_group');
    }

    public function forceDelete(AuthUser $user, Group $group): bool
    {
        return $user->can('force_delete_group');
    }

    public function forceDeleteAny(AuthUser $user): bool
    {
        return $user->can('force_delete_any_group');
    }

    public function restore(AuthUser $user, Group $group): bool
    {
        return $user->can('restore_group');
    }

    public function restoreAny(AuthUser $user): bool
    {
        return $user->can('restore_any_group');
    }
}
