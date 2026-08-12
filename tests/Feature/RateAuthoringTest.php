<?php

declare(strict_types=1);

use Liberu\Ecommerce\Shipping\Enums\BandAxis;
use Liberu\Ecommerce\Shipping\Enums\RateType;
use Liberu\Ecommerce\Shipping\Filament\Resources\RateResource\Pages\CreateRate;
use Liberu\Ecommerce\Shipping\Filament\Resources\RateResource\Pages\EditRate;
use Liberu\Ecommerce\Shipping\Models\Rate;
use Liberu\Ecommerce\Shipping\Models\ServiceLevel;
use Liberu\Ecommerce\Shipping\Models\Zone;
use Liberu\PackageTestbench\TestUser;
use Livewire\Livewire as LivewireTester;

beforeEach(function (): void {
    $this->actingAs(TestUser::factory()->create());

    $this->zone = Zone::query()->create(['tenant_id' => 'default', 'code' => 'uk', 'name' => 'UK', 'precedence' => 10]);
    $this->zone->territories()->create(['tenant_id' => 'default', 'country_code' => 'GB']);
    $this->serviceLevel = ServiceLevel::query()->create(['tenant_id' => 'default', 'code' => 'standard', 'name' => 'Standard']);
});

function rateForm(array $overrides = []): array
{
    return array_replace([
        'zone_id' => test()->zone->getKey(),
        'service_level_id' => test()->serviceLevel->getKey(),
        'rate_type' => RateType::Flat->value,
        'currency' => 'GBP',
        'amount' => '4.95',
        'transit_min_days' => 1,
        'transit_max_days' => 3,
        'transit_basis' => 'business_days',
        'is_active' => true,
    ], $overrides);
}

it('stores a flat amount as integer minor units taken from a decimal string', function (): void {
    LivewireTester::test(CreateRate::class)
        ->fillForm(rateForm(['amount' => '19.99']))
        ->call('create')
        ->assertHasNoFormErrors();

    $rate = Rate::query()->firstOrFail();

    // 1999, not the 1998 that `(int) (19.99 * 100)` produces.
    expect($rate->amount_minor)->toBe(1999)
        ->and($rate->amount_minor)->not->toBe((int) (19.99 * 100));
});

it('refuses a band set that leaves a gap, as form validation against the repeater', function (): void {
    $component = LivewireTester::test(CreateRate::class)
        ->fillForm(rateForm([
            'rate_type' => RateType::Table->value,
            'amount' => null,
            'band_axis' => BandAxis::WeightGrams->value,
            'bands' => [
                ['lower_bound' => 0, 'upper_bound' => 1000, 'is_unbounded' => false, 'amount' => '3.00'],
                ['lower_bound' => 2000, 'upper_bound' => null, 'is_unbounded' => true, 'amount' => '9.00'],
            ],
        ]))
        ->call('create')
        ->assertHasFormErrors(['bands']);

    expect($component->errors()->get('data.bands')[0])->toContain('gap between 1000 and 2000')
        ->and(Rate::query()->count())->toBe(0);
});

it('refuses a band set with no explicitly unbounded top band', function (): void {
    $component = LivewireTester::test(CreateRate::class)
        ->fillForm(rateForm([
            'rate_type' => RateType::Table->value,
            'amount' => null,
            'band_axis' => BandAxis::WeightGrams->value,
            'bands' => [
                ['lower_bound' => 0, 'upper_bound' => 1000, 'is_unbounded' => false, 'amount' => '3.00'],
                ['lower_bound' => 1000, 'upper_bound' => 5000, 'is_unbounded' => false, 'amount' => '9.00'],
            ],
        ]))
        ->call('create')
        ->assertHasFormErrors(['bands']);

    expect($component->errors()->get('data.bands')[0])->toContain('no unbounded top band');
});

it('refuses overlapping bands', function (): void {
    $component = LivewireTester::test(CreateRate::class)
        ->fillForm(rateForm([
            'rate_type' => RateType::Table->value,
            'amount' => null,
            'band_axis' => BandAxis::WeightGrams->value,
            'bands' => [
                ['lower_bound' => 0, 'upper_bound' => 2000, 'is_unbounded' => false, 'amount' => '3.00'],
                ['lower_bound' => 1000, 'upper_bound' => null, 'is_unbounded' => true, 'amount' => '9.00'],
            ],
        ]))
        ->call('create')
        ->assertHasFormErrors(['bands']);

    expect($component->errors()->get('data.bands')[0])->toContain('overlap');
});

it('accepts a band set that tiles the axis from zero', function (): void {
    LivewireTester::test(CreateRate::class)
        ->fillForm(rateForm([
            'rate_type' => RateType::Table->value,
            'amount' => null,
            'band_axis' => BandAxis::WeightGrams->value,
            'bands' => [
                ['lower_bound' => 0, 'upper_bound' => 1000, 'is_unbounded' => false, 'amount' => '3.00'],
                ['lower_bound' => 1000, 'upper_bound' => null, 'is_unbounded' => true, 'amount' => '9.50'],
            ],
        ]))
        ->call('create')
        ->assertHasNoFormErrors();

    $rate = Rate::query()->with('bands')->firstOrFail();

    expect($rate->bands)->toHaveCount(2)
        ->and($rate->bands->pluck('amount_minor')->all())->toBe([300, 950])
        ->and($rate->bands->last()->is_unbounded)->toBeTrue();
});

it('refuses a table rate with no band axis, against the field that fixes it', function (): void {
    LivewireTester::test(CreateRate::class)
        ->fillForm(rateForm([
            'rate_type' => RateType::Table->value,
            'amount' => null,
            'band_axis' => null,
            'bands' => [],
        ]))
        ->call('create')
        ->assertHasFormErrors(['rate_type']);
});

it('records a free-shipping threshold as a rate rule in minor units', function (): void {
    LivewireTester::test(CreateRate::class)
        ->fillForm(rateForm(['free_above_subtotal' => '50.00']))
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Rate::query()->firstOrFail()->free_above_subtotal_minor)->toBe(5000);
});

it('reloads amounts as decimal strings when editing, and re-saves them as the same integer', function (): void {
    LivewireTester::test(CreateRate::class)->fillForm(rateForm(['amount' => '4.05']))->call('create');

    $rate = Rate::query()->firstOrFail();
    $component = LivewireTester::test(EditRate::class, ['record' => $rate->getKey()]);

    expect($component->instance()->form->getRawState()['amount'])->toBe('4.05');

    $component->call('save')->assertHasNoFormErrors();

    expect($rate->refresh()->amount_minor)->toBe(405);
});
