# Owner defects — reproduction notes

| ID | Defect | Root cause (investigation) |
|---|---|---|
| A | Return paired strip flat + DEPARTURE/ARRIVAL text; lower green OUTBOUND/RETURN | `PairReturnCard` strip + duplicate labels |
| B | Traveler READY → skeleton flash | `PassengerDetailsPage.loadContext` always `setLoading(true)`; auto-revalidate / fare-accept soft reload flipped full skeleton |
| C | Checkout footer missing | `(checkout)/layout.tsx` passed `hideFooter` |
| D | Rounded logo tiles | Groups wrappers used bordered rounded boxes; shared mark needed |
| E/F | Groups plain list / weak hero | Landing + result cards text-heavy |
| G | Flat public page tops | `PublicPageHero` classical gradient block |
| H | Home gap + orphan green dots | `PublicHero` `AnimatedFlightPath` + oversized overlap spacer |

## Traveler glitch timeline

```
INITIAL_LOADING -> READY
(auto soft revalidate / query refresh must NOT return to INITIAL_LOADING)
```

TRAVELER_GLITCH_ROOT_CAUSE=`PassengerDetailsPage.loadContext unconditionally setLoading(true); secondary soft reloads after READY remounted full passenger-skeleton`
