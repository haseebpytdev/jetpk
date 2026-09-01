# Traveler glitch — root cause

TRAVELER_GLITCH_ROOT_CAUSE=`PassengerDetailsPage.loadContext always called setLoading(true); silent auto-revalidate and fare-accept paths re-invoked loadContext after READY, flipping the page back to full passenger-skeleton ("Preparing your trip…")`

## Fix authority model

- `loadContext({ soft: true })` does **not** set `loading=true`
- Full skeleton gate: `showInitialSkeleton = loading && !context`
- Soft reloads after READY keep form visible
- Auto-revalidate + accept-fare use soft reload

## Expected transitions

```
INITIAL_LOADING -> READY
```

Not:

```
INITIAL_LOADING -> READY -> INITIAL_LOADING
```

TRAVELER_READY_TO_FULL_SKELETON_REGRESSION=0 (contract + code path)
