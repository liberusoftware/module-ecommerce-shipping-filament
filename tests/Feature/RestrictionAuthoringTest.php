<?php

declare(strict_types=1);

use Liberu\Ecommerce\Shipping\Enums\RestrictionType;
use Liberu\Ecommerce\Shipping\Filament\Resources\RestrictionResource\Pages\CreateRestriction;
use Liberu\Ecommerce\Shipping\Filament\Resources\RestrictionResource\Pages\EditRestriction;
use Liberu\Ecommerce\Shipping\Models\Restriction;
use Liberu\PackageTestbench\TestUser;
use Livewire\Livewire as LivewireTester;

beforeEach(function (): void {
    $this->actingAs(TestUser::factory()->create());
});

function restrictionForm(array $overrides = []): array
{
    return array_replace([
        'restriction_type' => RestrictionType::MaxWeightGrams->value,
        'threshold' => 30_000,
        'reason_code' => 'over_max_weight',
        'reason' => 'This parcel is heavier than our courier will carry.',
        'is_active' => true,
    ], $overrides);
}

it('records the reason a buyer will be shown, not just the fact of exclusion', function (): void {
    LivewireTester::test(CreateRestriction::class)
        ->fillForm(restrictionForm())
        ->call('create')
        ->assertHasNoFormErrors();

    $restriction = Restriction::query()->firstOrFail();

    expect($restriction->reason)->toBe('This parcel is heavier than our courier will carry.')
        ->and($restriction->reason_code)->toBe('over_max_weight')
        ->and($restriction->threshold)->toBe(30_000)
        ->and($restriction->tenant_id)->toBe('default');
});

it('refuses a threshold-carrying restriction with no threshold, as form validation', function (): void {
    $component = LivewireTester::test(CreateRestriction::class)
        ->fillForm(restrictionForm(['threshold' => null]))
        ->call('create')
        ->assertHasFormErrors(['threshold']);

    expect($component->errors()->get('data.threshold'))->not->toBeEmpty()
        ->and(Restriction::query()->count())->toBe(0);
});

it('refuses a negative threshold', function (): void {
    LivewireTester::test(CreateRestriction::class)
        ->fillForm(restrictionForm(['threshold' => -1]))
        ->call('create')
        ->assertHasFormErrors(['threshold']);

    expect(Restriction::query()->count())->toBe(0);
});

it('accepts a destination-excluded restriction with no threshold at all', function (): void {
    LivewireTester::test(CreateRestriction::class)
        ->fillForm(restrictionForm([
            'restriction_type' => RestrictionType::DestinationExcluded->value,
            'threshold' => null,
            'reason_code' => 'no_service',
            'reason' => 'We do not ship to this destination.',
        ]))
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Restriction::query()->firstOrFail()->restriction_type)->toBe(RestrictionType::DestinationExcluded);
});

it('edits a restriction through the domain action', function (): void {
    LivewireTester::test(CreateRestriction::class)->fillForm(restrictionForm())->call('create');

    $restriction = Restriction::query()->firstOrFail();

    LivewireTester::test(EditRestriction::class, ['record' => $restriction->getKey()])
        ->fillForm(['threshold' => 25_000])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($restriction->refresh()->threshold)->toBe(25_000);
});
