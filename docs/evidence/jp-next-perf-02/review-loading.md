# Review loading

REVIEW_ROOT_CAUSE=client-only `/booking/review?format=json` with full-page `BookingLoadingState` and no SSR payload.  
Fix: `BookingPageShell` + header while loading; soft reload after READY.
