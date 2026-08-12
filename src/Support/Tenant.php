<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Filament\Support;

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Config;

/**
 * Where this surface gets the tenant the domain demands as an explicit argument.
 *
 * The fleet has no contract for this — the domain takes an argument, `-api`
 * reads it off the actor, and a Filament panel reads it off the panel. That
 * inconsistency is recorded under "Known limits" in docs/domain.md rather than
 * settled here.
 *
 * `Config::get()` rather than `config()`: the helper is a framework-*foundation*
 * function and is not provided by anything this package declares.
 */
final class Tenant
{
    public static function current(): string
    {
        $tenant = Filament::getTenant();

        if ($tenant !== null) {
            return (string) $tenant->getKey();
        }

        return (string) Config::get('ecommerce-shipping.tenant_id', 'default');
    }
}
