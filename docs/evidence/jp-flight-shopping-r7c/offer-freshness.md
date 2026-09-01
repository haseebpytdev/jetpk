# Offer freshness

Selectable offers carry search_id, supplier_provider, offer refs, freshness meta (created, refresh_due, stale_at, revalidation_status).

Live RT continue after fix: freshness success, high_risk_cached_offer=false, requires_revalidation_before_checkout=false.

MATERIAL_EDIT_REUSES_INCOMPATIBLE_OFFERS=NO  
STALE_FARE_LOOP=0 (search rematch creates new authority)
