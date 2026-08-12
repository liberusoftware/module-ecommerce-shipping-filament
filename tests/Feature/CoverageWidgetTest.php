<?php

declare(strict_types=1);

use Liberu\Ecommerce\Shipping\Contracts\FetchesCarrierRates;
use Liberu\Ecommerce\Shipping\Filament\Tests\Fixtures\FakeCarrier;
use Liberu\Ecommerce\Shipping\Filament\Widgets\ShippingCoverageOverview;
use Liberu\Ecommerce\Shipping\Models\Rate;
use Liberu\Ecommerce\Shipping\Models\ServiceLevel;
use Liberu\Ecommerce\Shipping\Models\Zone;
use Liberu\PackageTestbench\TestUser;
use Livewire\Livewire as LivewireTester;

beforeEach(function (): void {
    $this->actingAs(TestUser::factory()->create());
});

it('shows an active zone with nothing priced in it as the operator fault it is', function (): void {
    Zone::query()->create(['tenant_id' => 'default', 'code' => 'uk', 'name' => 'UK', 'precedence' => 10]);

    LivewireTester::test(ShippingCoverageOverview::class)
        ->assertSee('Zones with nothing priced')
        ->assertSee('A destination these cover gets no option at all');
});

it('stops complaining once every active zone prices something', function (): void {
    $zone = Zone::query()->create(['tenant_id' => 'default', 'code' => 'uk', 'name' => 'UK', 'precedence' => 10]);
    $level = ServiceLevel::query()->create(['tenant_id' => 'default', 'code' => 'std', 'name' => 'Standard']);

    Rate::query()->create([
        'tenant_id' => 'default',
        'zone_id' => $zone->getKey(),
        'service_level_id' => $level->getKey(),
        'rate_type' => 'flat',
        'amount_minor' => 495,
        'currency' => 'GBP',
        'transit_min_days' => 1,
        'transit_max_days' => 3,
        'transit_basis' => 'business_days',
    ]);

    LivewireTester::test(ShippingCoverageOverview::class)
        ->assertSee('Every active zone prices at least one service level')
        ->assertDontSee('A destination these cover gets no option at all');
});

it('reports live carrier rating as switched off when nothing is bound', function (): void {
    LivewireTester::test(ShippingCoverageOverview::class)
        ->assertSee('Switched off')
        ->assertSee('A supported configuration, not an error');
});

it('reports live carrier rating as enabled when something is bound', function (): void {
    $this->app->bind(FetchesCarrierRates::class, fn (): FakeCarrier => new FakeCarrier());

    LivewireTester::test(ShippingCoverageOverview::class)
        ->assertSee('Enabled')
        ->assertDontSee('A supported configuration, not an error');
});

it('counts only this tenant', function (): void {
    Zone::query()->create(['tenant_id' => 'somebody-else', 'code' => 'x', 'name' => 'X', 'precedence' => 1]);

    LivewireTester::test(ShippingCoverageOverview::class)->assertSuccessful();

    expect(Zone::query()->where('tenant_id', 'default')->count())->toBe(0);
});
