# What this package owns

It owns **presentation**. Every rule, every refusal and every stored fact belongs to
`liberusoftware/ecommerce-shipping`; this package calls its actions and queries and renders
their answers.

The line is worth stating precisely, because the temptation on an operator surface is to
"just check that first" and end up with a second, drifting copy of the rule:

| Belongs to the domain | Belongs here |
|---|---|
| Whether two zones overlap ambiguously | Turning that refusal into a form error against the field that fixes it |
| Whether a band set tiles its axis | The same, against the bands repeater |
| Which zone a destination matches | Asking, and saying what the answer means |
| What a shipping price is, and whether it is derived or quoted | Showing which, and its provenance |
| The total charge as a fold over lines | Rendering the fold's result; never storing it |
| That a selected price is immutable | Refusing every mutating ability before the gate is consulted |

## The two write-time refusals

`ZoneOverlapsExistingZone` and `RateBandsDoNotTileAxis` are raised by `SaveZone` and
`SaveRate`. Zones, rates and bands are authored here and nowhere else, so this is the only
surface that can turn them into something an operator can act on.

Both are caught in a trait on the create and edit pages and rethrown as
`Illuminate\Validation\ValidationException` keyed to the form's state path — `data.precedence`
and `data.bands` respectively. Livewire puts the message under the field, and the record is
never written. The domain's message already names the conflicting zone, the axis and the
offending bounds, so the message is passed through rather than reworded: two places phrasing
the same refusal is two places to get it wrong.

`InvalidRateDefinition` is handled the same way, against `data.rate_type` and
`data.threshold`, because "a table rate needs an axis" is also a form mistake.

## Read-only means the funnel, not the button

`isReadOnly()` is a `RelationManager` **instance** method. On a `Resource` it is a comment.

`ShippingPriceResource::getAuthorizationResponse()` is overridden instead. Every `can*()`,
every `authorize*()` and every action Filament builds passes through it, so denying there
denies everywhere — including when a host installs a permissive `Gate::before`, and
including for abilities no policy answers.

`AdjustmentsRelationManager` does both: `isReadOnly()` because on that class it means
something, and the instance `getAuthorizationResponse()` because `isReadOnly()` only covers
the actions Filament builds for you, while `associate` and `dissociate` are live on a
`hasMany` and default open.

Underneath both, `ReadOnlyPolicy` answers all eighteen abilities by name and
`AuthoringPolicy` reopens exactly four of them. Nothing is left to the framework's default,
because the framework's default is yes.

## The preview records nothing

Quoting is a write: `QuoteShippingOptions` records an offered price per option, with its
parcels. An operator checking their own rules must not leave priced offers behind, so the
preview runs the call inside a transaction it aborts with a private exception. The result
object survives because the closure captures it by reference — an arrow function would have
captured the `null` it started with.

The weight typed into the preview is a diagnostic. A shopper surface is *told* its parcels
through `ResolvesParcels` and never names a weight or a price; the operator using this page
authored the rates it is testing.

## Known limits

- **The fleet has four implementations of tenancy and no contract.** The domain takes
  `tenantId` as an explicit argument, `-api` reads it off the actor, and this package reads
  `Filament::getTenant()`. Where a panel has no tenancy configured it falls back to
  `config('ecommerce-shipping.tenant_id')`, defaulting to `default`. This is recorded, not
  settled: settling it is a fleet decision, not a wave-10 one.
- **"The carrier is down right now" is not a persistent fact, and this surface does not
  pretend it is.** The coverage widget reports whether `FetchesCarrierRates` is *bound*, which
  is a deployment fact. Degradation is a per-quote outcome and is rendered on the preview,
  where it actually happened. The domain emits `CarrierRatingDegraded`; a host that wants a
  panel-wide health indicator should listen for it and store something, which is a decision
  about retention that this package will not make on its behalf.
- **Money is fixed at exponent 2 on this surface.** The domain's `Money` supports 0–6, but
  no shipping table carries an exponent column, so every amount authored or displayed here
  uses the default. A zero-exponent currency would need a schema change in the domain first.
- **The preview cannot exercise `ResolvesParcels`.** That seam takes a basket reference, and
  a basket belongs to Cart. The `-api` package tests the unbound-resolver path; this one
  takes the parcel directly.
