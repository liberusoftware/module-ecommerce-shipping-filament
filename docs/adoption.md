# Adopting this package

## 1. Composer

Neither this package nor the domain package is on Packagist. Composer honours `repositories`
**only from the root manifest**, so the host must add both entries itself — the ones in this
package's own `composer.json` work for its CI and do nothing for a consumer:

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/liberusoftware/module-ecommerce-shipping" },
    { "type": "vcs", "url": "https://github.com/liberusoftware/module-ecommerce-shipping-filament" }
]
```

```bash
composer require liberusoftware/ecommerce-shipping-filament:^0.1
```

That pulls `liberusoftware/ecommerce-shipping` with it.

## 2. Enablement

This package ships **no** `extra.laravel.providers`. Installing it boots nothing. The host's
`ModuleManagerServiceProvider` globs `config('modules.paths')` for `*/module.json` and
registers only modules named in `MODULES_ENABLED`:

```dotenv
MODULES_ENABLED=…,ecommerce-shipping,ecommerce-shipping-filament
```

Enable the domain module too, or there are no tables.

## 3. The panel

Nothing registers globally. Attach the plugin to whichever panels should carry the surface:

```php
use Liberu\Ecommerce\Shipping\Filament\ShippingFilamentPlugin;

public function panel(Panel $panel): Panel
{
    return $panel->plugins([ShippingFilamentPlugin::make()]);
}
```

The manifest names the `admin` panel in `presentation.filament`, which is a statement about
where it is *expected* to go, not an automatic registration.

## 4. Tenancy

Resources scope every query to `Filament::getTenant()`, and stamp it on write. A panel
without tenancy configured falls back to `config('ecommerce-shipping.tenant_id')`, default
`'default'`. A single-tenant host can leave that alone; a multi-tenant host should configure
panel tenancy and let the panel answer.

A tenant id is never read from a form. `CreateServiceLevel` overwrites whatever the browser
sent, and there is a test for it.

## 5. Authorization

The provider registers a policy for each of the seven shipping models with the gate.
Laravel's discovery looks for `App\Policies\…`, which a package cannot satisfy, and a model
with no policy is **exposed**: the unanswered gate is permissive.

If the host already maps its own policies for these models, its `AuthServiceProvider`
registration will win or lose depending on boot order — decide deliberately. Note that
`ShippingPriceResource` denies mutation *before* consulting the gate at all, so a host
policy cannot accidentally reopen editing a recorded price.

## 6. What moves off the host

The host's shipping code is replaced, not wrapped. Specifically:

- `ShippingService::calculateDistanceRate()` is **deleted, not reimplemented**. This module
  computes no distance, geocodes nothing, and has no mileage rate. A zone is a set of
  destination predicates.
- `shipping_methods` becomes `shipping_service_levels` plus `shipping_rates`. The two tables
  are different shapes and may coexist while a store migrates; nothing here reads the host's.
- `estimated_delivery_time` free text becomes an integer transit-day range and a basis. There
  is no migration for "next week".
- `PruneShippingQuotes` must be removed. A selected price is evidence and is never swept; the
  domain's `SweepExpiredPrices` deletes only *unselected*, expired offers.
- `config('shipping.drop_shipping_premium')` must be removed. A surcharge is a recorded
  adjustment line with its own reason, visible on the price's page, not a config float added
  to an authoritative stored quote.
- `customer_groups.free_shipping_threshold` **moves here**, as `free_above_subtotal_minor` on
  a rate: "shipping costs zero in this zone above this order subtotal" is a row in a rate
  table. `discounts.type = 'free_shipping'` does **not** move here — a coupon that makes
  shipping free is `ecommerce-promotions`. The host has both implementations today and
  nothing reconciles them; adopting this module is the moment to decide which one was meant.

## 7. What this package will not do

It will not print a label, buy postage, track a package, or tell you when something will
arrive as a date. Everything after the buyer says yes belongs to Fulfillment.
