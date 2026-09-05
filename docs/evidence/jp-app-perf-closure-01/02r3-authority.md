# JP-PERF-FINAL-02R3 evidence

CLIENT_TIMING_USED_AS_COMMERCIAL_AUTHORITY=NO
SERVER_AUTHORITY_FOR_REPRICE_SKIP=YES

Skip requires passengers itinerary:
- authoritative_after_revalidation === true
- bound_search_id / bound_offer_id match selection
- selected_fare_option_key match

sessionStorage jp-book-now-timing remains instrumentation only.

Harness records every revalidate-offer POST:
BOOK_NOW_REVALIDATION_POST_COUNT (before document assign)
TRAVELER_AUTO_REPRICE_POST_COUNT (after)
Redundant traveler posts counted only when itinerary authority is true.
