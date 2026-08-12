<?php

declare(strict_types=1);

use Liberu\Ecommerce\Shipping\Carrier\CarrierDoesNotServeDestination;
use Liberu\Ecommerce\Shipping\Carrier\CarrierRatesReturned;
use Liberu\Ecommerce\Shipping\Carrier\CarrierRatingDisabled;
use Liberu\Ecommerce\Shipping\Carrier\CarrierRatingOutcome;
use Liberu\Ecommerce\Shipping\Carrier\CarrierRatingUnavailable;
use Liberu\Ecommerce\Shipping\Data\CarrierRate;
use Liberu\Ecommerce\Shipping\Data\Money;
use Liberu\Ecommerce\Shipping\Enums\QuoteOutcome;
use Liberu\Ecommerce\Shipping\Filament\Support\OutcomeCopy;

it('gives every quote outcome its own heading', function (): void {
    $headings = array_map(
        static fn (QuoteOutcome $outcome): string => OutcomeCopy::forQuote($outcome, 'uk')['heading'],
        QuoteOutcome::cases(),
    );

    expect($headings)->toHaveCount(4)
        ->and(array_unique($headings))->toHaveCount(4);
});

it('gives every carrier outcome its own heading, which is the whole point of the seam', function (): void {
    $outcomes = [
        new CarrierRatingDisabled(),
        new CarrierRatingUnavailable('acme', 'timed out'),
        new CarrierDoesNotServeDestination('acme'),
        new CarrierRatesReturned([new CarrierRate('acme', 'EXP', 'Express', new Money(100, 'GBP'))]),
    ];

    $headings = array_map(static fn ($outcome): string => OutcomeCopy::forCarrier($outcome)['heading'], $outcomes);

    expect($headings)->toHaveCount(4)
        ->and(array_unique($headings))->toHaveCount(4);
});

it('names the zone in the operator fault, so the copy is actionable and not a shrug', function (): void {
    $copy = OutcomeCopy::forQuote(QuoteOutcome::NoRatesConfigured, 'eu-west');

    expect($copy['body'])->toContain('eu-west')
        ->and($copy['body'])->toContain('add a rate')
        ->and($copy['tone'])->toBe('danger');
});

it('does not call live rating being switched off an error', function (): void {
    $copy = OutcomeCopy::forCarrier(new CarrierRatingDisabled());

    expect($copy['tone'])->toBe('gray')
        ->and($copy['body'])->toContain('not an error');
});

it('carries the failure reason into the degraded notice', function (): void {
    $copy = OutcomeCopy::forCarrier(new CarrierRatingUnavailable('acme', 'connect timeout after 5s'));

    expect($copy['body'])->toContain('connect timeout after 5s')
        ->and($copy['tone'])->toBe('danger');
});

it('falls back to the outcome code rather than guessing, if the seam ever grows a case', function (): void {
    $unknown = new class() implements CarrierRatingOutcome
    {
        public function code(): string
        {
            return 'carrier_partially_rated';
        }
    };

    expect(OutcomeCopy::forCarrier($unknown)['body'])->toContain('carrier_partially_rated');
});
