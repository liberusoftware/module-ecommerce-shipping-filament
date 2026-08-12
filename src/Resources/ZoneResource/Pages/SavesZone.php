<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Filament\Resources\ZoneResource\Pages;

use Illuminate\Support\Facades\App;
use Illuminate\Validation\ValidationException;
use Liberu\Ecommerce\Shipping\Actions\SaveZone;
use Liberu\Ecommerce\Shipping\Exceptions\ZoneOverlapsExistingZone;
use Liberu\Ecommerce\Shipping\Filament\Resources\ZoneResource;
use Liberu\Ecommerce\Shipping\Filament\Support\Tenant;
use Liberu\Ecommerce\Shipping\Models\Zone;

/**
 * The write-time overlap refusal, surfaced as form validation.
 *
 * `ZoneOverlapsExistingZone` reaching an operator as a stack trace is a failure
 * of this package: the domain names both zones and the precedence they collide
 * at, and that message belongs against the field that fixes it.
 */
trait SavesZone
{
    /** @param  array<string, mixed>  $data */
    protected function saveZone(array $data, ?int $zoneId): Zone
    {
        try {
            return App::make(SaveZone::class)(Tenant::current(), ZoneResource::definitionFrom($data), $zoneId);
        } catch (ZoneOverlapsExistingZone $refusal) {
            throw ValidationException::withMessages([
                'data.precedence' => $refusal->getMessage(),
            ]);
        }
    }
}
