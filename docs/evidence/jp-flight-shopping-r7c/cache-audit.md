# Cache audit

SEARCH_CACHE_LAYERS=client-state,Next-nav,Laravel-search-store,Sabre-freshness-meta,revalidation-gate  
SEARCH_CACHE_ROOT_CAUSE=NO  
SEARCH_CACHE_TTL=search-store session TTL (existing)  
OFFER_CACHE_TTL=refresh_due 300s / stale_after 600s (SabreOfferFreshness)  
REPRICE_CACHE_TTL=revalidation window ~600s when successful  

No `Cache::flush` / Redis FLUSHALL / global no-cache.
