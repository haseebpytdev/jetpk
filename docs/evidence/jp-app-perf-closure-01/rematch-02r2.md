# Rematch>1 on 02aa6249 / beN-xjWZyGE35QwBcetEg (N=30)

2/30 samples rematch=2. POST#1 = fare-selection prevalidation (before Book Now).
POST#2 timestamp == T11_TRAVELER_USABLE (after passengers GET). Same search_id/offer_id.

Cause: PassengerDetailsPage silent auto-reprice when price_needs_refresh, despite Book Now FRESH/JOINED.

Classification: OTHER (traveler auto-reprice) not Book Now KEY_MISMATCH.

Fix: skip auto-reprice when jp-book-now-timing source is FRESH or JOINED.
Harness: rematch_count ignores POSTs after passengers document nav.
