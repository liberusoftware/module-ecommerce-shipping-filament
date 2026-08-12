# liberusoftware/ecommerce-shipping-filament

The Filament 5 operator surface for [`liberusoftware/ecommerce-shipping`](https://github.com/liberusoftware/module-ecommerce-shipping).

> **Shipping owns what a shipment costs and how long it is expected to take. It owns no
> order, no parcel's contents, no label, and no package in motion.**
>
> Draw the line at *the moment of purchase*: shipping answers "what will this cost and how
> long will it take", and everything after the buyer says yes belongs to Fulfillment.

This package adds no rule of its own. It is where an operator **authors** the rules the
domain enforces, and where they **read** the prices it recorded.

## What this is

| Surface | Purpose |
|---|---|
| `ZoneResource` | Destination zones and their territories. Overlap at equal precedence is refused as form validation. |
| `ServiceLevelResource` | What the host called a "shipping method". A rate prices one of these in one zone. |
| `RateResource` | Flat and table rates, their bands, and the free-shipping threshold. A band set that does not tile its axis is refused as form validation. |
| `RestrictionResource` | Exclusions, each carrying the reason a buyer is shown. |
| `ShippingPriceResource` | Recorded prices. **Read-only through every ability**, with an adjustments relation manager that is equally read-only. |
| `RatePreview` page | "What would this destination be offered, and why." Runs the real quote and records nothing. |
| `ShippingCoverageOverview` widget | The operator faults worth seeing without going looking: an active zone with nothing priced in it, and whether live carrier rating is on. |

## Installation

This package ships **no** `extra.laravel.providers`. Installing it boots nothing; the host's
`ModuleManagerServiceProvider` enables it by name. See [docs/adoption.md](docs/adoption.md).

Attach the plugin to whichever panel should carry the surface:

```php
use Liberu\Ecommerce\Shipping\Filament\ShippingFilamentPlugin;

public function panel(Panel $panel): Panel
{
    return $panel->plugins([ShippingFilamentPlugin::make()]);
}
```

## The two write-time refusals this surface owns

The domain refuses an ambiguous zone overlap and a band set that fails to tile its axis, at
write time, because **ordering resolved at read time is ordering nobody can audit**. Those
refusals are raised where they are authored — here — so they arrive as form validation with
the conflicting row named, never as a 500 from an uncaught domain exception:

- `ZoneOverlapsExistingZone` → an error on the **precedence** field naming both zone codes
  and the precedence they collide at.
- `RateBandsDoNotTileAxis` → an error on the **bands** repeater naming the axis and the exact
  pair of bounds that gap or overlap.

## Recorded prices are read-only, and the enforcement is not a comment

`isReadOnly()` is a `RelationManager` **instance** method. Declaring it on a `Resource`
enforces nothing at all. `ShippingPriceResource` overrides `getAuthorizationResponse()` —
the single funnel every `can*()` and `authorize*()` passes through — and denies every
ability outside `viewAny` and `view`.

The suite proves it the only way that means anything: it installs
`Gate::before(fn () => true)`, **asserts the gate answers yes**, and then asserts
`canEdit`, `canDelete` and `canCreate` are still false. A test without that middle assertion
proves only that nothing granted the ability in the first place.

Every model is mapped to a policy that answers all eighteen abilities by name. A model with
no policy is exposed, not safe: Laravel's unanswered gate is permissive, Filament's
`get_authorization_response()` returns **allow** when a policy that exists lacks the method
asked about, and `associate`/`dissociate` are live on a `hasMany` and default open.

## Three carrier outcomes, never one empty list

The host returned `[]` for live rating being switched off, for the carrier being down, and
for the carrier not serving the address — then silently billed a flat rate in all three
cases. The preview renders four visibly different states:

| Outcome | What it says |
|---|---|
| not bound | Live carrier rating is switched off. A supported configuration, **not an error**. |
| bound, threw | The carrier is unavailable **right now**, with the reason. Derived rates only, and degraded. |
| bound, answered with nothing | The carrier **does not serve this destination**. A settled fact, not an outage. |
| bound, answered with rates | Recorded verbatim with provenance, because a quoted price is irreproducible. |

## Four quote outcomes, and one of them is your fault

`no_rates_configured` — a zone matched and nothing is priced in it — is an **operator**
fault, and this is the operator surface. It gets its own words, names the zone, and says
what to do about it. It is not the same message as "no zone covers this destination", and
the widget counts it so you find out before a buyer does.

## Money, weight and distance

Money is integer minor units everywhere; the form takes a decimal string and converts it
with string arithmetic, because `(int) (19.99 * 100)` is `1998`. Weight is integer grams,
dimensions integer millimetres, percentages integer basis points. There is no unit selector
anywhere in this package, and no distance: a zone is a set of destination predicates, never
a radius.

An estimate is an integer transit-day range plus its basis. **This module does not compute a
delivery date** — that needs a ship date, a cut-off time and a holiday calendar it does not
own.

## What this replaces

The host at `ecommerce-laravel` offered every shipping method to every address on earth at
the same price, threw the destination away, priced in floats, disagreed with itself about
weight units three ways, accepted free-text delivery estimates, deleted the evidence for a
charged price on a schedule, and collapsed every carrier failure mode into one empty array.
The domain package documents all twelve faults; this package is where the replacement for
the first four is authored and the replacement for the last one is seen.

## Documentation

- [docs/domain.md](docs/domain.md) — what this surface owns, and its known limits
- [docs/adoption.md](docs/adoption.md) — installing it into a host
- [docs/runbook.md](docs/runbook.md) — the operator's actual jobs

## Licence

MIT. See [LICENSE.md](LICENSE.md).
