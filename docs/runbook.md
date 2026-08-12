# Runbook

The operator's actual jobs, and what each screen is telling you.

## "A customer says they were offered nothing"

Open **Rate preview**, enter their country, subdivision and postcode, and a plausible parcel
weight in grams. The page runs the same quote the shopper surface runs, and rolls it back —
it records nothing and changes nothing. You will get exactly one of four answers:

| What you see | What it means | What to do |
|---|---|---|
| **Options available** | Working. | Compare the prices with what they were quoted. |
| **Every option was excluded by a restriction** | A zone matched and priced, and a restriction removed every option. Each exclusion names the restriction and the reason the buyer was shown. | Decide whether that restriction is right. If it is, the buyer got a correct answer. |
| **No zone covers this destination** | No active zone has a territory matching that address. | Add a territory to an existing zone, or create one. Or, if you genuinely do not ship there, say so with a `destination_excluded` restriction so the buyer gets a reason instead of a blank. |
| **A zone matched, but nothing is priced in it** | **Your fault, not theirs.** The zone covers the address and has no active rate. | Add a rate for the named zone, or deactivate the zone so a wider one can match. |

The last row is the one that used to be invisible. The **Shipping coverage** widget counts it
so you find out before a buyer does.

## "Is live carrier rating working?"

Two different questions, answered in two different places, because they are two different
facts.

*Is it switched on for this deployment?* — the **Shipping coverage** widget. "Switched off"
means no `FetchesCarrierRates` implementation is bound. That is a supported configuration,
not an outage: you get derived rates only.

*Did it work on this quote?* — the **Rate preview**, which reports one of:

- **switched off** — as above, nothing to fix.
- **unavailable right now** — it is bound and it failed on this attempt, with the reason
  shown. Prices shown alongside are derived only and are degraded. Check the carrier's status
  and your credentials.
- **does not serve this destination** — the carrier answered, and had nothing for that
  address. Settled fact about where they deliver; not an outage, and retrying will not help.
- **answered with rates** — working.

The domain emits `CarrierRatingDegraded` on the second of those. If you want alerting, listen
for it in the host.

## "The zone I am saving is refused"

> Zone [x] would match [GB/SW] at precedence 10, which zone [y] already matches.

Two active zones that could match the same destination at the same precedence are ambiguous,
and ambiguity resolved at read time is ordering nobody can audit. Either give one of them a
different precedence — higher wins — or narrow its territories. The message names both zones;
you do not have to go looking.

Deactivating a zone lifts it out of the check, so a zone you are still drafting can be saved
inactive.

## "The bands I am saving are refused"

> Bands on [weight_grams] leave a gap between 1000 and 2000.

Bands are half-open `[lower, upper)` and must tile the axis from zero with exactly one
explicitly unbounded top band. The common mistakes and their messages:

- **gap** — the next band starts above where the previous one ended.
- **overlap** — the next band starts below where the previous one ended.
- **no unbounded top band** — every band is bounded. Tick "Unbounded top band" on the last
  one; a blank upper bound does *not* imply it.
- **more than one unbounded band** — only the last band may be unbounded.
- **a band that contains nothing** — upper bound at or below the lower bound.
- **does not start at 0** — the lowest band must start at zero.

## "Why were we charged this?"

Open the price under **Shipping prices**. Every recorded price says which kind it is, and the
two kinds mean genuinely different things:

- **derived** — computed from rules this module holds. The provenance line names the zone, the
  rate and the rule applied. It is reproducible from the rules as recorded.
- **quoted** — a third party's answer at an instant about a future physical movement. The
  provenance line names the carrier, the service and the instant. **It is irreproducible.**
  Asking again in a minute may give a different number; asking while the carrier is down gives
  none. That is why it is stored verbatim and never recomputed, never adjusted in place, and
  never pruned once selected.

The **Total charge** is a fold over the price line and its adjustment lines. There is no
stored total anywhere. A surcharge appears as its own adjustment row with its own reason, not
folded into the price — the host's habit of adding a config float to a stored quote is
exactly the defect this replaces.

## "I need to correct a recorded price"

You cannot, and nothing on this surface will let you. Editing, deleting, replicating and
restoring are denied at the authorization funnel, before the gate is consulted, for prices
and for their adjustment lines. If the charge was wrong, record a **new** adjustment through
the domain with a reason that says so; the history stays readable.

Prices that were only ever *offered* and have expired are swept by the domain's
`SweepExpiredPrices`. Prices that were *selected* are evidence and stay forever.

## Money, weight, and units

Amounts are typed as decimal strings — `4.95` — and stored as integer minor units. Weight is
integer grams; dimensions are integer millimetres. There is no unit selector, because three
units that disagree is what the host had: `products.weight` with no unit at all,
`product_variants.weight_unit` defaulting to kilograms, and a config defaulting to ounces.
Convert at the edge, in the adapter that needs the other unit, and test the conversion there.

## Estimates are not dates

An estimate is a transit-day range and a basis: "1-3 business days". This module does not and
will not compute a delivery date, because that needs a ship date, a cut-off time and a
holiday calendar it does not own.
