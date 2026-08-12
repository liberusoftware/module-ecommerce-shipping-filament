<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Filament\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * The four rule tables an operator authors. Everything else stays denied by name.
 */
abstract class AuthoringPolicy extends ReadOnlyPolicy
{
    public function create(?Authenticatable $user): bool
    {
        return true;
    }

    public function update(?Authenticatable $user, Model $record): bool
    {
        return true;
    }

    public function delete(?Authenticatable $user, Model $record): bool
    {
        return true;
    }

    public function deleteAny(?Authenticatable $user): bool
    {
        return true;
    }
}
