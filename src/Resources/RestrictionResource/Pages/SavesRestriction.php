<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Filament\Resources\RestrictionResource\Pages;

use Illuminate\Support\Facades\App;
use Illuminate\Validation\ValidationException;
use Liberu\Ecommerce\Shipping\Actions\SaveRestriction;
use Liberu\Ecommerce\Shipping\Exceptions\InvalidRateDefinition;
use Liberu\Ecommerce\Shipping\Filament\Resources\RestrictionResource;
use Liberu\Ecommerce\Shipping\Filament\Support\Tenant;
use Liberu\Ecommerce\Shipping\Models\Restriction;

trait SavesRestriction
{
    /** @param  array<string, mixed>  $data */
    protected function saveRestriction(array $data, ?int $restrictionId): Restriction
    {
        try {
            return App::make(SaveRestriction::class)(Tenant::current(), RestrictionResource::definitionFrom($data), $restrictionId);
        } catch (InvalidRateDefinition $refusal) {
            throw ValidationException::withMessages([
                'data.threshold' => $refusal->getMessage(),
            ]);
        }
    }
}
