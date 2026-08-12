<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Filament\Resources\RateResource\Pages;

use Illuminate\Support\Facades\App;
use Illuminate\Validation\ValidationException;
use Liberu\Ecommerce\Shipping\Actions\SaveRate;
use Liberu\Ecommerce\Shipping\Exceptions\InvalidRateDefinition;
use Liberu\Ecommerce\Shipping\Exceptions\RateBandsDoNotTileAxis;
use Liberu\Ecommerce\Shipping\Filament\Resources\RateResource;
use Liberu\Ecommerce\Shipping\Filament\Support\Tenant;
use Liberu\Ecommerce\Shipping\Models\Rate;

/**
 * The band-tiling refusal, surfaced as form validation against the repeater.
 *
 * The domain's message names the axis and the exact pair of bounds that gap or
 * overlap; an operator who sees a stack trace instead has been given nothing.
 */
trait SavesRate
{
    /** @param  array<string, mixed>  $data */
    protected function saveRate(array $data, ?int $rateId): Rate
    {
        try {
            return App::make(SaveRate::class)(Tenant::current(), RateResource::definitionFrom($data), $rateId);
        } catch (RateBandsDoNotTileAxis $refusal) {
            throw ValidationException::withMessages([
                'data.bands' => $refusal->getMessage(),
            ]);
        } catch (InvalidRateDefinition $refusal) {
            throw ValidationException::withMessages([
                'data.rate_type' => $refusal->getMessage(),
            ]);
        }
    }
}
