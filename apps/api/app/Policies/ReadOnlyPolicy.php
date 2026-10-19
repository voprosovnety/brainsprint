<?php

namespace App\Policies;

use App\Models\AdminUser;
use Illuminate\Database\Eloquent\Model;

class ReadOnlyPolicy
{
    public function viewAny(AdminUser $user): bool
    {
        return $user->is_admin;
    }

    public function view(AdminUser $user, Model $record): bool
    {
        return $user->is_admin;
    }

    public function create(AdminUser $user): bool
    {
        return false;
    }

    public function update(AdminUser $user, Model $record): bool
    {
        return false;
    }

    public function delete(AdminUser $user, Model $record): bool
    {
        return false;
    }

    public function deleteAny(AdminUser $user): bool
    {
        return false;
    }

    public function replicate(AdminUser $user, Model $record): bool
    {
        return false;
    }

    public function restore(AdminUser $user, Model $record): bool
    {
        return false;
    }

    public function forceDelete(AdminUser $user, Model $record): bool
    {
        return false;
    }
}
