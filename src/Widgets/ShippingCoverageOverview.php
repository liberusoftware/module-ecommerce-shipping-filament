<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\App;
use Liberu\Ecommerce\Shipping\Contracts\FetchesCarrierRates;
use Liberu\Ecommerce\Shipping\Enums\PriceStatus;
use Liberu\Ecommerce\Shipping\Filament\Support\Tenant;
use Liberu\Ecommerce\Shipping\Models\Restriction;
use Liberu\Ecommerce\Shipping\Models\ServiceLevel;
use Liberu\Ecommerce\Shipping\Models\ShippingPrice;
use Liberu\Ecommerce\Shipping\Models\Zone;

/**
 * The operator faults worth seeing without going looking for them.
 *
 * An active zone with no active rate is the fourth quote outcome waiting to
 * happen: a destination it covers gets nothing priced, and nothing else in the
 * panel would tell you.
 */
class ShippingCoverageOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'Shipping coverage';

    /** @return array<int, Stat> */
    protected function getStats(): array
    {
        $tenant = Tenant::current();

        $zones = Zone::query()->where('tenant_id', $tenant)->where('is_active', true)->count();
        $unpriced = Zone::query()
            ->where('tenant_id', $tenant)
            ->where('is_active', true)
            ->whereDoesntHave('rates', fn ($query) => $query->where('is_active', true))
            ->count();

        return [
            Stat::make('Active zones', (string) $zones)
                ->description('A zone is a set of destination predicates, never a radius.'),
            Stat::make('Zones with nothing priced', (string) $unpriced)
                ->description($unpriced === 0
                    ? 'Every active zone prices at least one service level.'
                    : 'A destination these cover gets no option at all. Add a rate, or deactivate the zone.')
                ->color($unpriced === 0 ? 'success' : 'danger'),
            Stat::make('Active service levels', (string) ServiceLevel::query()->where('tenant_id', $tenant)->where('is_active', true)->count()),
            Stat::make('Active restrictions', (string) Restriction::query()->where('tenant_id', $tenant)->where('is_active', true)->count())
                ->description('Each one refuses with a recorded reason a buyer is shown.'),
            Stat::make('Selected prices', (string) ShippingPrice::query()->where('tenant_id', $tenant)->where('status', PriceStatus::Selected->value)->count())
                ->description('Evidence. Immutable, and never swept.'),
            Stat::make('Live carrier rating', App::bound(FetchesCarrierRates::class) ? 'Enabled' : 'Switched off')
                ->description(App::bound(FetchesCarrierRates::class)
                    ? 'A carrier rating implementation is bound. An outage is reported per quote, not here.'
                    : 'No implementation is bound. A supported configuration, not an error: derived rates only.')
                ->color(App::bound(FetchesCarrierRates::class) ? 'success' : 'gray'),
        ];
    }
}
