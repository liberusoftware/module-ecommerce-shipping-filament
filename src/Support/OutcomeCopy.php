<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Filament\Support;

use Liberu\Ecommerce\Shipping\Carrier\CarrierDoesNotServeDestination;
use Liberu\Ecommerce\Shipping\Carrier\CarrierRatesReturned;
use Liberu\Ecommerce\Shipping\Carrier\CarrierRatingDisabled;
use Liberu\Ecommerce\Shipping\Carrier\CarrierRatingOutcome;
use Liberu\Ecommerce\Shipping\Carrier\CarrierRatingUnavailable;
use Liberu\Ecommerce\Shipping\Enums\QuoteOutcome;

/**
 * Every outcome gets its own words. None of them is an empty list.
 *
 * The host returned `[]` for live rating being switched off, for the carrier
 * being down, and for the carrier not serving the address, then silently billed
 * a flat rate in all three cases. Telling them apart is most of why this module
 * exists, so the copy is data here — one place, asserted distinct by a test,
 * rather than four `@if`s in a template that can drift into saying the same
 * thing twice.
 *
 * `no_rates_configured` is an **operator** fault: a zone matched and nothing is
 * priced in it. This is the operator surface, so it says so and says what to do.
 */
final class OutcomeCopy
{
    /** @return array{tone: string, heading: string, body: string} */
    public static function forQuote(QuoteOutcome $outcome, ?string $zoneCode): array
    {
        $zone = $zoneCode ?? 'the matched zone';

        return match ($outcome) {
            QuoteOutcome::OptionsAvailable => [
                'tone' => 'success',
                'heading' => 'Options available',
                'body' => "Zone [{$zone}] priced the destination below.",
            ],
            QuoteOutcome::AllExcluded => [
                'tone' => 'warning',
                'heading' => 'Every option was excluded by a restriction',
                'body' => "Zone [{$zone}] has rates, and a restriction excluded every one of them. Each exclusion below names the restriction that did it — a buyer is shown the same reason, never a blank list.",
            ],
            QuoteOutcome::NoZoneMatched => [
                'tone' => 'danger',
                'heading' => 'No zone covers this destination',
                'body' => 'No active zone has a territory matching it. Add a territory to an existing zone, or create a new one — or accept that you do not ship there and say so with a destination-excluded restriction.',
            ],
            QuoteOutcome::NoRatesConfigured => [
                'tone' => 'danger',
                'heading' => 'A zone matched, but nothing is priced in it',
                'body' => "Zone [{$zone}] covers this destination and has no active rate. This is a configuration fault on your side, not a gap in the buyer's address: add a rate for zone [{$zone}], or deactivate the zone so a wider one can match.",
            ],
        };
    }

    /** @return array{tone: string, heading: string, body: string} */
    public static function forCarrier(CarrierRatingOutcome $outcome): array
    {
        return match (true) {
            $outcome instanceof CarrierRatingDisabled => [
                'tone' => 'gray',
                'heading' => 'Live carrier rating is switched off',
                'body' => 'No carrier rating implementation is bound in this deployment. That is a supported configuration, not an error: only derived rates are offered.',
            ],
            $outcome instanceof CarrierRatingUnavailable => [
                'tone' => 'danger',
                'heading' => 'The carrier is unavailable right now',
                'body' => "Live rating is switched on and failed on this attempt, so the prices below are derived only and are degraded. Reason: {$outcome->reason}",
            ],
            $outcome instanceof CarrierDoesNotServeDestination => [
                'tone' => 'warning',
                'heading' => 'The carrier does not serve this destination',
                'body' => "Carrier [{$outcome->carrierCode}] answered, and had nothing for this address. This is a settled fact about where they deliver, not an outage.",
            ],
            $outcome instanceof CarrierRatesReturned => [
                'tone' => 'success',
                'heading' => 'The carrier answered with rates',
                'body' => 'Each one is recorded verbatim with its provenance. A quoted price is irreproducible: asking again in a minute may give a different number.',
            ],
            default => [
                'tone' => 'gray',
                'heading' => 'Unrecognised carrier outcome',
                'body' => 'The carrier seam answered with an outcome this surface does not know: '.$outcome->code(),
            ],
        };
    }
}
