<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Filament\Tests\Fixtures;

use Liberu\Ecommerce\Shipping\Carrier\CarrierRatingOutcome;
use Liberu\Ecommerce\Shipping\Contracts\FetchesCarrierRates;
use Liberu\Ecommerce\Shipping\Data\Destination;
use Liberu\Ecommerce\Shipping\Data\ParcelSet;
use RuntimeException;

/**
 * Answers with whichever outcome the test asked for, or throws.
 *
 * The seam's whole point is that "switched off", "down right now" and "does not
 * serve this address" are three different answers; a fake that could only
 * return an empty list would be the host's bug in test form.
 */
final class FakeCarrier implements FetchesCarrierRates
{
    public function __construct(private ?CarrierRatingOutcome $outcome = null) {}

    public function fetch(string $tenantId, Destination $destination, ParcelSet $parcels): CarrierRatingOutcome
    {
        if ($this->outcome === null) {
            throw new RuntimeException('the carrier API timed out');
        }

        return $this->outcome;
    }
}
