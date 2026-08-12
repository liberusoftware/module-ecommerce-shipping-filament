<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Filament;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Liberu\Ecommerce\Shipping\Filament\Policies\PriceAdjustmentPolicy;
use Liberu\Ecommerce\Shipping\Filament\Policies\PriceParcelPolicy;
use Liberu\Ecommerce\Shipping\Filament\Policies\RatePolicy;
use Liberu\Ecommerce\Shipping\Filament\Policies\RestrictionPolicy;
use Liberu\Ecommerce\Shipping\Filament\Policies\ServiceLevelPolicy;
use Liberu\Ecommerce\Shipping\Filament\Policies\ShippingPricePolicy;
use Liberu\Ecommerce\Shipping\Filament\Policies\ZonePolicy;
use Liberu\Ecommerce\Shipping\Models\PriceAdjustment;
use Liberu\Ecommerce\Shipping\Models\PriceParcel;
use Liberu\Ecommerce\Shipping\Models\Rate;
use Liberu\Ecommerce\Shipping\Models\Restriction;
use Liberu\Ecommerce\Shipping\Models\ServiceLevel;
use Liberu\Ecommerce\Shipping\Models\ShippingPrice;
use Liberu\Ecommerce\Shipping\Models\Zone;

/**
 * Registers nothing globally beyond policies and views.
 *
 * The panel UI arrives only where an application attaches
 * {@see ShippingFilamentPlugin}, per panel. Laravel's policy discovery looks
 * for `App\Policies\…`, which a package can never satisfy, so the mapping is
 * declared here: a model whose policy is never found is exposed, because the
 * unanswered gate is permissive.
 */
final class ShippingFilamentServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public const POLICIES = [
        Zone::class => ZonePolicy::class,
        ServiceLevel::class => ServiceLevelPolicy::class,
        Rate::class => RatePolicy::class,
        Restriction::class => RestrictionPolicy::class,
        ShippingPrice::class => ShippingPricePolicy::class,
        PriceAdjustment::class => PriceAdjustmentPolicy::class,
        PriceParcel::class => PriceParcelPolicy::class,
    ];

    public function boot(): void
    {
        foreach (self::POLICIES as $model => $policy) {
            Gate::policy($model, $policy);
        }

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'ecommerce-shipping-filament');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../resources/views' => $this->app->resourcePath('views/vendor/ecommerce-shipping-filament'),
            ], 'ecommerce-shipping-filament-views');
        }
    }
}
