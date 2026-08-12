<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Liberu\Ecommerce\Shipping\Enums\PriceKind;
use Liberu\Ecommerce\Shipping\Enums\PriceStatus;
use Liberu\Ecommerce\Shipping\Filament\Resources\ShippingPriceResource;
use Liberu\Ecommerce\Shipping\Filament\Resources\ShippingPriceResource\Pages\ListShippingPrices;
use Liberu\Ecommerce\Shipping\Filament\Resources\ShippingPriceResource\Pages\ViewShippingPrice;
use Liberu\Ecommerce\Shipping\Filament\Resources\ShippingPriceResource\RelationManagers\AdjustmentsRelationManager;
use Liberu\Ecommerce\Shipping\Models\PriceAdjustment;
use Liberu\Ecommerce\Shipping\Models\ShippingPrice;
use Liberu\PackageTestbench\TestUser;
use Livewire\Livewire as LivewireTester;

beforeEach(function (): void {
    $this->actingAs(TestUser::factory()->create());

    $this->price = ShippingPrice::query()->create([
        'tenant_id' => 'default',
        'reference' => 'shp_readonly',
        'kind' => PriceKind::Quoted,
        'status' => PriceStatus::Selected,
        'amount_minor' => 1250,
        'currency' => 'GBP',
        'service_level_code' => 'express',
        'service_level_name' => 'Express',
        'destination_country' => 'GB',
        'carrier_code' => 'acme',
        'carrier_service_code' => 'ACME_EXP',
        'carrier_rate_reference' => 'upstream_rate_123',
        'quoted_at' => now(),
        'expires_at' => now()->addHour(),
        'selected_at' => now(),
    ]);

    $this->adjustment = $this->price->adjustments()->create([
        'tenant_id' => 'default',
        'amount_minor' => 200,
        'currency' => 'GBP',
        'reason_code' => 'remote_area',
        'reason' => 'Remote area surcharge.',
    ]);
});

it('keeps every mutating ability false even when the gate answers yes to everything', function (): void {
    Gate::before(fn (): bool => true);

    // The middle assertion is the whole test. Without it, the two around it
    // prove only that nothing granted the abilities in the first place.
    expect(Gate::allows('update', $this->price))->toBeTrue()
        ->and(Gate::allows('delete', $this->price))->toBeTrue()
        ->and(Gate::allows('create', ShippingPrice::class))->toBeTrue();

    expect(ShippingPriceResource::canEdit($this->price))->toBeFalse()
        ->and(ShippingPriceResource::canDelete($this->price))->toBeFalse()
        ->and(ShippingPriceResource::canCreate())->toBeFalse()
        ->and(ShippingPriceResource::canDeleteAny())->toBeFalse()
        ->and(ShippingPriceResource::canForceDelete($this->price))->toBeFalse()
        ->and(ShippingPriceResource::canForceDeleteAny())->toBeFalse()
        ->and(ShippingPriceResource::canReplicate($this->price))->toBeFalse()
        ->and(ShippingPriceResource::canReorder())->toBeFalse()
        ->and(ShippingPriceResource::canRestore($this->price))->toBeFalse()
        ->and(ShippingPriceResource::canRestoreAny())->toBeFalse();
});

it('still allows reading with a permissive gate, so the denial is targeted and not a blanket', function (): void {
    Gate::before(fn (): bool => true);

    expect(ShippingPriceResource::canViewAny())->toBeTrue()
        ->and(ShippingPriceResource::canView($this->price))->toBeTrue();
});

it('denies mutation through the single funnel every can* passes through', function (string $ability): void {
    Gate::before(fn (): bool => true);

    $response = ShippingPriceResource::getAuthorizationResponse($ability, $this->price);

    expect($response->denied())->toBeTrue()
        ->and($response->message())->toContain('evidence');
})->with(['create', 'update', 'delete', 'deleteAny', 'forceDelete', 'forceDeleteAny', 'restore', 'restoreAny', 'replicate', 'reorder', 'associate', 'attach', 'detach', 'detachAny', 'dissociate', 'dissociateAny']);

it('publishes no route that could edit or create a recorded price', function (): void {
    expect(array_keys(ShippingPriceResource::getPages()))->toBe(['index', 'view']);
});

it('shuts associate and dissociate on the adjustments relation manager, which default open on a hasMany', function (string $ability): void {
    Gate::before(fn (): bool => true);

    $manager = LivewireTester::test(AdjustmentsRelationManager::class, [
        'ownerRecord' => $this->price,
        'pageClass' => ViewShippingPrice::class,
    ])->instance();

    expect($manager->getAuthorizationResponse($ability, $this->adjustment)->denied())->toBeTrue();
})->with(['create', 'update', 'delete', 'deleteAny', 'associate', 'attach', 'detach', 'detachAny', 'dissociate', 'dissociateAny', 'forceDelete', 'restore', 'replicate', 'reorder']);

it('reports the adjustments relation manager as read-only, which is the one class where that method means anything', function (): void {
    $manager = LivewireTester::test(AdjustmentsRelationManager::class, [
        'ownerRecord' => $this->price,
        'pageClass' => ViewShippingPrice::class,
    ])->instance();

    expect($manager->isReadOnly())->toBeTrue()
        ->and($manager->getAuthorizationResponse('viewAny')->allowed())->toBeTrue();
});

it('shows the total as a fold over the price line and its adjustment lines', function (): void {
    expect(ShippingPriceResource::total($this->price->refresh()))->toBe('GBP 14.50');

    $this->price->adjustments()->create([
        'tenant_id' => 'default',
        'amount_minor' => -50,
        'currency' => 'GBP',
        'reason_code' => 'goodwill',
        'reason' => 'Goodwill reduction.',
    ]);

    expect(ShippingPriceResource::total($this->price->refresh()))->toBe('GBP 14.00');
});

it('describes a quoted price by its carrier provenance and a derived one by its rules', function (): void {
    expect(ShippingPriceResource::provenance($this->price))->toContain('carrier acme');

    $derived = ShippingPrice::query()->create([
        'tenant_id' => 'default',
        'reference' => 'shp_derived',
        'kind' => PriceKind::Derived,
        'status' => PriceStatus::Offered,
        'amount_minor' => 495,
        'currency' => 'GBP',
        'service_level_code' => 'standard',
        'service_level_name' => 'Standard',
        'destination_country' => 'GB',
        'zone_id' => 1,
        'zone_code' => 'uk',
        'rate_id' => 1,
        'applied_rule' => 'flat',
        'expires_at' => now()->addHour(),
    ]);

    expect(ShippingPriceResource::provenance($derived))->toContain('zone uk')
        ->and(ShippingPriceResource::provenance($derived))->not->toContain('carrier');
});

it('lists recorded prices and opens one for reading', function (): void {
    LivewireTester::test(ListShippingPrices::class)
        ->assertCanSeeTableRecords([$this->price])
        ->assertSuccessful();

    LivewireTester::test(ViewShippingPrice::class, ['record' => $this->price->getKey()])
        ->assertSuccessful();
});

it('hides another tenant\'s recorded price', function (): void {
    ShippingPrice::query()->create([
        'tenant_id' => 'somebody-else',
        'reference' => 'shp_theirs',
        'kind' => PriceKind::Derived,
        'status' => PriceStatus::Offered,
        'amount_minor' => 100,
        'currency' => 'GBP',
        'service_level_code' => 'standard',
        'service_level_name' => 'Standard',
        'destination_country' => 'GB',
        'expires_at' => now()->addHour(),
    ]);

    expect(ShippingPriceResource::getEloquentQuery()->pluck('reference')->all())->toBe(['shp_readonly']);
});

it('shows the parcels a price was quoted against, in grams and millimetres', function (): void {
    expect(LivewireTester::test(ViewShippingPrice::class, ['record' => $this->price->getKey()])->html())
        ->toContain('None recorded');

    $this->price->parcels()->create([
        'tenant_id' => 'default',
        'weight_grams' => 1000,
        'length_mm' => 200,
        'width_mm' => 150,
        'height_mm' => 100,
    ]);

    expect(LivewireTester::test(ViewShippingPrice::class, ['record' => $this->price->getKey()])->html())
        ->toContain('1000 g');
});

it('keeps the adjustment lines readable and their amounts formatted from minor units', function (): void {
    expect(PriceAdjustment::query()->firstOrFail()->money()->decimal())->toBe('2.00');
});
