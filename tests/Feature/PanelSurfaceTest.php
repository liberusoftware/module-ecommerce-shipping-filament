<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Liberu\Ecommerce\Shipping\Filament\Pages\RatePreview;
use Liberu\Ecommerce\Shipping\Filament\Resources\RateResource\Pages\ListRates;
use Liberu\Ecommerce\Shipping\Filament\Resources\RestrictionResource\Pages\ListRestrictions;
use Liberu\Ecommerce\Shipping\Filament\Resources\ServiceLevelResource;
use Liberu\Ecommerce\Shipping\Filament\Resources\ServiceLevelResource\Pages\CreateServiceLevel;
use Liberu\Ecommerce\Shipping\Filament\Resources\ServiceLevelResource\Pages\EditServiceLevel;
use Liberu\Ecommerce\Shipping\Filament\Resources\ServiceLevelResource\Pages\ListServiceLevels;
use Liberu\Ecommerce\Shipping\Filament\Resources\ZoneResource\Pages\ListZones;
use Liberu\Ecommerce\Shipping\Filament\ShippingFilamentPlugin;
use Liberu\Ecommerce\Shipping\Models\Rate;
use Liberu\Ecommerce\Shipping\Models\ServiceLevel;
use Liberu\Ecommerce\Shipping\Models\Zone;
use Liberu\PackageTestbench\TestUser;
use Livewire\Livewire as LivewireTester;

beforeEach(function (): void {
    $this->actingAs(TestUser::factory()->create());
});

it('registers exactly the surface the manifest promises, on the panel that attached the plugin', function (): void {
    $panel = Filament::getPanel('admin');

    expect($panel->getPlugin('ecommerce-shipping'))->toBeInstanceOf(ShippingFilamentPlugin::class);

    foreach (ShippingFilamentPlugin::RESOURCES as $resource) {
        expect($panel->getResources())->toContain($resource);
    }

    foreach (ShippingFilamentPlugin::PAGES as $page) {
        expect($panel->getPages())->toContain($page);
    }

    foreach (ShippingFilamentPlugin::WIDGETS as $widget) {
        expect($panel->getWidgets())->toContain($widget);
    }
});

it('names in module.json only classes that exist', function (): void {
    $manifest = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/module.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest['presentation']['filament'])->not->toBeEmpty();

    foreach ($manifest['presentation']['filament'] as $plugins) {
        foreach ($plugins as $plugin) {
            expect(class_exists($plugin))->toBeTrue();
        }
    }
});

it('renders every list page', function (string $page): void {
    LivewireTester::test($page)->assertSuccessful();
})->with([ListZones::class, ListServiceLevels::class, ListRates::class, ListRestrictions::class]);

it('renders the rate preview page', function (): void {
    LivewireTester::test(RatePreview::class)->assertSuccessful();
});

it('authors a service level and stamps the tenant from the panel rather than the form', function (): void {
    LivewireTester::test(CreateServiceLevel::class)
        ->fillForm([
            'code' => 'express',
            'name' => 'Express',
            'description' => 'Next business day.',
            'is_active' => true,
            'tenant_id' => 'somebody-else',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(ServiceLevel::query()->firstOrFail()->tenant_id)->toBe('default');
});

it('edits a service level', function (): void {
    $level = ServiceLevel::query()->create(['tenant_id' => 'default', 'code' => 'std', 'name' => 'Standard']);

    LivewireTester::test(EditServiceLevel::class, ['record' => $level->getKey()])
        ->fillForm(['name' => 'Standard delivery'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($level->refresh()->name)->toBe('Standard delivery');
});

it('scopes every authoring resource query to the panel tenant', function (): void {
    $ours = Zone::query()->create(['tenant_id' => 'default', 'code' => 'ours', 'name' => 'Ours', 'precedence' => 1]);
    $theirs = Zone::query()->create(['tenant_id' => 'somebody-else', 'code' => 'theirs', 'name' => 'Theirs', 'precedence' => 1]);

    ServiceLevel::query()->create(['tenant_id' => 'somebody-else', 'code' => 'theirs', 'name' => 'Theirs']);

    expect(ServiceLevelResource::getEloquentQuery()->count())->toBe(0);

    $level = ServiceLevel::query()->create(['tenant_id' => 'default', 'code' => 'ours', 'name' => 'Ours']);

    Rate::query()->create([
        'tenant_id' => 'somebody-else',
        'zone_id' => $theirs->getKey(),
        'service_level_id' => $level->getKey(),
        'rate_type' => 'flat',
        'amount_minor' => 100,
        'currency' => 'GBP',
        'transit_min_days' => 1,
        'transit_max_days' => 1,
        'transit_basis' => 'business_days',
    ]);

    LivewireTester::test(ListRates::class)->assertCanNotSeeTableRecords(Rate::query()->get());
    LivewireTester::test(ListZones::class)
        ->assertCanSeeTableRecords([$ours])
        ->assertCanNotSeeTableRecords([$theirs]);
});
