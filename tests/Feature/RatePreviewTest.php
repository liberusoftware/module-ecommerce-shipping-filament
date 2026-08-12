<?php

declare(strict_types=1);

use Liberu\Ecommerce\Shipping\Carrier\CarrierDoesNotServeDestination;
use Liberu\Ecommerce\Shipping\Carrier\CarrierRatesReturned;
use Liberu\Ecommerce\Shipping\Contracts\FetchesCarrierRates;
use Liberu\Ecommerce\Shipping\Data\CarrierRate;
use Liberu\Ecommerce\Shipping\Data\Money;
use Liberu\Ecommerce\Shipping\Data\TransitEstimate;
use Liberu\Ecommerce\Shipping\Enums\TransitBasis;
use Liberu\Ecommerce\Shipping\Filament\Pages\RatePreview;
use Liberu\Ecommerce\Shipping\Filament\Tests\Fixtures\FakeCarrier;
use Liberu\Ecommerce\Shipping\Models\Rate;
use Liberu\Ecommerce\Shipping\Models\Restriction;
use Liberu\Ecommerce\Shipping\Models\ServiceLevel;
use Liberu\Ecommerce\Shipping\Models\ShippingPrice;
use Liberu\Ecommerce\Shipping\Models\Zone;
use Liberu\PackageTestbench\TestUser;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire as LivewireTester;

beforeEach(function (): void {
    $this->actingAs(TestUser::factory()->create());
});

function ukZone(): Zone
{
    $zone = Zone::query()->create(['tenant_id' => 'default', 'code' => 'uk', 'name' => 'UK', 'precedence' => 10]);
    $zone->territories()->create(['tenant_id' => 'default', 'country_code' => 'GB']);

    return $zone;
}

function standardRate(Zone $zone): Rate
{
    $serviceLevel = ServiceLevel::query()->create(['tenant_id' => 'default', 'code' => 'standard', 'name' => 'Standard']);

    return Rate::query()->create([
        'tenant_id' => 'default',
        'zone_id' => $zone->getKey(),
        'service_level_id' => $serviceLevel->getKey(),
        'rate_type' => 'flat',
        'amount_minor' => 495,
        'currency' => 'GBP',
        'transit_min_days' => 1,
        'transit_max_days' => 3,
        'transit_basis' => TransitBasis::BusinessDays->value,
    ]);
}

function previewGb(): Testable
{
    return LivewireTester::test(RatePreview::class)
        ->fillForm(['country_code' => 'GB', 'currency' => 'GBP', 'weight_grams' => 1000])
        ->call('preview');
}

it('names the operator fault when a zone matches and nothing is priced in it', function (): void {
    ukZone();

    previewGb()
        ->assertSee('A zone matched, but nothing is priced in it')
        ->assertSee('no_rates_configured')
        ->assertSee('add a rate for zone [uk]')
        ->assertDontSee('No zone covers this destination');
});

it('says something different when no zone covers the destination at all', function (): void {
    previewGb()
        ->assertSee('No zone covers this destination')
        ->assertSee('no_zone_matched')
        ->assertDontSee('A zone matched, but nothing is priced in it');
});

it('lists the options a covered, priced destination would be offered', function (): void {
    standardRate(ukZone());

    previewGb()
        ->assertSee('Options available')
        ->assertSee('Standard')
        ->assertSee('4.95')
        ->assertSee('1-3 business days');
});

it('shows the restriction that excluded every option, rather than a blank list', function (): void {
    standardRate(ukZone());

    Restriction::query()->create([
        'tenant_id' => 'default',
        'restriction_type' => 'max_weight_grams',
        'threshold' => 500,
        'reason_code' => 'over_max_weight',
        'reason' => 'Heavier than our courier will carry.',
    ]);

    previewGb()
        ->assertSee('Every option was excluded by a restriction')
        ->assertSee('all_excluded')
        ->assertSee('Heavier than our courier will carry.')
        ->assertSee('over_max_weight');
});

it('states plainly that live carrier rating is off, which is a configuration and not an error', function (): void {
    standardRate(ukZone());

    previewGb()
        ->assertSee('Live carrier rating is switched off')
        ->assertSee('carrier_rating_disabled')
        ->assertDontSee('unavailable right now');
});

it('marks the result degraded when a bound carrier fails on this attempt', function (): void {
    standardRate(ukZone());
    $this->app->bind(FetchesCarrierRates::class, fn (): FakeCarrier => new FakeCarrier());

    previewGb()
        ->assertSee('The carrier is unavailable right now')
        ->assertSee('carrier_unavailable')
        ->assertSee('the carrier API timed out')
        ->assertDontSee('Live carrier rating is switched off');
});

it('tells not-served apart from unavailable', function (): void {
    standardRate(ukZone());
    $this->app->bind(
        FetchesCarrierRates::class,
        fn (): FakeCarrier => new FakeCarrier(new CarrierDoesNotServeDestination('acme')),
    );

    previewGb()
        ->assertSee('The carrier does not serve this destination')
        ->assertSee('carrier_does_not_serve_destination')
        ->assertSee('not an outage')
        ->assertDontSee('The carrier is unavailable right now');
});

it('renders a carrier answer as its own outcome, alongside the derived options', function (): void {
    standardRate(ukZone());
    $this->app->bind(FetchesCarrierRates::class, fn (): FakeCarrier => new FakeCarrier(new CarrierRatesReturned([
        new CarrierRate(
            'acme',
            'ACME_EXP',
            'Acme Express',
            new Money(1250, 'GBP'),
            new TransitEstimate(1, 1, TransitBasis::BusinessDays),
            'upstream_rate_123',
        ),
    ])));

    previewGb()
        ->assertSee('The carrier answered with rates')
        ->assertSee('carrier_rates_returned')
        ->assertSee('Acme Express')
        ->assertSee('12.50')
        ->assertSee('irreproducible');
});

it('records nothing at all, because a preview is a question and not an offer', function (): void {
    standardRate(ukZone());

    previewGb()->assertSee('Options available');

    expect(ShippingPrice::query()->count())->toBe(0);
});

it('reports a refused input as a refusal instead of a stack trace', function (): void {
    LivewireTester::test(RatePreview::class)
        ->fillForm(['country_code' => 'GB', 'currency' => 'GBP', 'weight_grams' => 0])
        ->call('preview')
        ->assertSee('The preview was refused')
        ->assertSuccessful();
});

it('says the total shipping charge is a fold and never a stored column', function (): void {
    standardRate(ukZone());

    previewGb()->assertSee('A preview records nothing');
});
