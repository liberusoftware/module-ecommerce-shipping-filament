# Changelog

All notable changes to this package are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this package
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - 2026-08-12

### Added

- `ShippingFilamentPlugin`, attached per panel by the application. Nothing in
  this package registers globally, and installing it boots nothing.
- Zone authoring, including territories, with the domain's write-time overlap
  refusal surfaced as **form validation on the precedence field**, naming both
  colliding zone codes. A domain exception reaching an operator as a stack
  trace is a failure of this package, so it is asserted not to happen.
- Rate and band authoring, with the domain's band-tiling refusal surfaced as
  form validation on the bands repeater. A gap, an overlap, an empty band and a
  missing explicitly unbounded top band are each refused with the axis and the
  offending bounds named.
- Service level and restriction authoring. A restriction's reason is a required
  field, because it is what a buyer is shown in place of the excluded option.
- Money entered as a decimal string and converted to integer minor units by
  string arithmetic. `19.99` stores `1999`, and a test asserts it is not the
  `1998` that `(int) (19.99 * 100)` produces. A float is refused at the type
  boundary of the conversion helper.
- `ShippingPriceResource`: recorded prices, read-only through every ability.
  Enforced by overriding `getAuthorizationResponse()` — the single funnel every
  `can*()` passes through — rather than by `isReadOnly()`, which is a
  `RelationManager` instance method and enforces nothing on a `Resource`.
- An adjustments relation manager that is read-only by `isReadOnly()`, by the
  ability funnel, and by a policy, with `associate` and `dissociate` shut by
  name because they are live on a `hasMany` and default open.
- A policy for every shipping model, answering all eighteen abilities
  explicitly, registered with the gate by the provider — Laravel's discovery
  looks in `App\Policies`, which a package can never satisfy.
- `RatePreview`: runs the real quote against a destination and a parcel and
  **records nothing**, by aborting the transaction it runs in. It renders the
  four quote outcomes and the four carrier outcomes as visibly different
  states, and `no_rates_configured` names the zone and says what to do, because
  it is an operator fault and this is the operator surface.
- `ShippingCoverageOverview`: counts active zones with nothing priced in them,
  and reports whether live carrier rating is bound in this deployment.

[0.1.0]: https://github.com/liberusoftware/module-ecommerce-shipping-filament/releases/tag/0.1.0
