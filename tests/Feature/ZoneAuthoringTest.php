<?php

declare(strict_types=1);

use Liberu\Ecommerce\Shipping\Exceptions\ZoneOverlapsExistingZone;
use Liberu\Ecommerce\Shipping\Filament\Resources\ZoneResource;
use Liberu\Ecommerce\Shipping\Filament\Resources\ZoneResource\Pages\CreateZone;
use Liberu\Ecommerce\Shipping\Filament\Resources\ZoneResource\Pages\EditZone;
use Liberu\Ecommerce\Shipping\Models\Zone;
use Liberu\PackageTestbench\TestUser;
use Livewire\Livewire as LivewireTester;

beforeEach(function (): void {
    $this->actingAs(TestUser::factory()->create());
});

function zoneForm(array $overrides = []): array
{
    return array_replace([
        'code' => 'uk-mainland',
        'name' => 'UK mainland',
        'precedence' => 10,
        'is_active' => true,
        'territories' => [
            ['country_code' => 'GB', 'subdivision_code' => null, 'postcode_prefix' => null],
        ],
    ], $overrides);
}

it('authors a zone with its territories through the domain action', function (): void {
    LivewireTester::test(CreateZone::class)
        ->fillForm(zoneForm())
        ->call('create')
        ->assertHasNoFormErrors();

    $zone = Zone::query()->where('code', 'uk-mainland')->firstOrFail();

    expect($zone->tenant_id)->toBe('default')
        ->and($zone->precedence)->toBe(10)
        ->and($zone->territories)->toHaveCount(1)
        ->and($zone->territories->first()->country_code)->toBe('GB');
});

it('refuses an ambiguous overlap as form validation naming the conflicting zone', function (): void {
    LivewireTester::test(CreateZone::class)
        ->fillForm(zoneForm())
        ->call('create')
        ->assertHasNoFormErrors();

    $component = LivewireTester::test(CreateZone::class)
        ->fillForm(zoneForm(['code' => 'uk-rival', 'name' => 'UK rival']))
        ->call('create')
        ->assertHasFormErrors(['precedence']);

    $errors = $component->errors()->get('data.precedence');

    expect($errors)->not->toBeEmpty()
        ->and($errors[0])->toContain('uk-rival')
        ->and($errors[0])->toContain('uk-mainland');

    // The refusal is validation, not a 500: the second zone was never written.
    expect(Zone::query()->count())->toBe(1);
});

it('lets the overlap through when the precedence differs, because precedence is what resolves it', function (): void {
    LivewireTester::test(CreateZone::class)->fillForm(zoneForm())->call('create')->assertHasNoFormErrors();

    LivewireTester::test(CreateZone::class)
        ->fillForm(zoneForm(['code' => 'uk-islands', 'name' => 'UK islands', 'precedence' => 20]))
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Zone::query()->count())->toBe(2);
});

it('refuses an overlap on edit too, and leaves the stored precedence untouched', function (): void {
    LivewireTester::test(CreateZone::class)->fillForm(zoneForm())->call('create');
    LivewireTester::test(CreateZone::class)
        ->fillForm(zoneForm(['code' => 'uk-islands', 'name' => 'UK islands', 'precedence' => 20]))
        ->call('create');

    $islands = Zone::query()->where('code', 'uk-islands')->firstOrFail();

    LivewireTester::test(EditZone::class, ['record' => $islands->getKey()])
        ->fillForm(['precedence' => 10])
        ->call('save')
        ->assertHasFormErrors(['precedence']);

    expect($islands->refresh()->precedence)->toBe(20);
});

it('loads the stored territories back into the repeater when editing', function (): void {
    LivewireTester::test(CreateZone::class)
        ->fillForm(zoneForm([
            'territories' => [
                ['country_code' => 'GB', 'subdivision_code' => null, 'postcode_prefix' => 'SW'],
                ['country_code' => 'IE', 'subdivision_code' => null, 'postcode_prefix' => null],
            ],
        ]))
        ->call('create');

    $zone = Zone::query()->where('code', 'uk-mainland')->firstOrFail();

    $state = LivewireTester::test(EditZone::class, ['record' => $zone->getKey()])
        ->instance()
        ->form
        ->getRawState();

    expect($state['territories'])->toHaveCount(2)
        ->and(array_column(array_values($state['territories']), 'country_code'))->toBe(['GB', 'IE']);
});

it('never lets the domain refusal escape as an unhandled exception', function (): void {
    LivewireTester::test(CreateZone::class)->fillForm(zoneForm())->call('create');

    // Nothing here asserts on a rendered 500 page: the point is that the second
    // save raises no ZoneOverlapsExistingZone out of the Livewire call at all.
    $raised = null;

    try {
        LivewireTester::test(CreateZone::class)
            ->fillForm(zoneForm(['code' => 'uk-rival']))
            ->call('create');
    } catch (ZoneOverlapsExistingZone $refusal) {
        $raised = $refusal;
    }

    expect($raised)->toBeNull();
});

it('scopes the resource query to the panel tenant', function (): void {
    Zone::query()->create(['tenant_id' => 'default', 'code' => 'ours', 'name' => 'Ours', 'precedence' => 1]);
    Zone::query()->create(['tenant_id' => 'somebody-else', 'code' => 'theirs', 'name' => 'Theirs', 'precedence' => 1]);

    expect(ZoneResource::getEloquentQuery()->pluck('code')->all())->toBe(['ours']);
});
