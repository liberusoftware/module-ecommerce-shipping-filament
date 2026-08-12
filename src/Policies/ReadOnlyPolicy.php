<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Filament\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Every ability a Filament resource or relation manager can ask about, named.
 *
 * A model with no policy is exposed, not safe: Laravel's unanswered gate is
 * permissive, and Filament's `get_authorization_response()` returns **allow**
 * when a policy that exists lacks the method asked about. `associate` and
 * `dissociate` are live on a `hasMany` and default open, which is why they are
 * here beside the obvious ones rather than left to the framework.
 *
 * The list is the contract. A subclass reopens an ability by overriding it,
 * deliberately and by name.
 */
abstract class ReadOnlyPolicy
{
    public function viewAny(?Authenticatable $user): bool
    {
        return true;
    }

    public function view(?Authenticatable $user, Model $record): bool
    {
        return true;
    }

    public function create(?Authenticatable $user): bool
    {
        return false;
    }

    public function update(?Authenticatable $user, Model $record): bool
    {
        return false;
    }

    public function delete(?Authenticatable $user, Model $record): bool
    {
        return false;
    }

    public function deleteAny(?Authenticatable $user): bool
    {
        return false;
    }

    public function restore(?Authenticatable $user, Model $record): bool
    {
        return false;
    }

    public function restoreAny(?Authenticatable $user): bool
    {
        return false;
    }

    public function forceDelete(?Authenticatable $user, Model $record): bool
    {
        return false;
    }

    public function forceDeleteAny(?Authenticatable $user): bool
    {
        return false;
    }

    public function replicate(?Authenticatable $user, Model $record): bool
    {
        return false;
    }

    public function reorder(?Authenticatable $user): bool
    {
        return false;
    }

    public function associate(?Authenticatable $user): bool
    {
        return false;
    }

    public function attach(?Authenticatable $user): bool
    {
        return false;
    }

    public function detach(?Authenticatable $user, Model $record): bool
    {
        return false;
    }

    public function detachAny(?Authenticatable $user): bool
    {
        return false;
    }

    public function dissociate(?Authenticatable $user, Model $record): bool
    {
        return false;
    }

    public function dissociateAny(?Authenticatable $user): bool
    {
        return false;
    }
}
