# Duplicate requests / effects / RSC

Post-fix groups: SSR_CLIENT_DOUBLE_FETCH_CRITICAL=0 for inventory/facets when key matches.  
CMS still client-fetched (secondary, non-blocking).  
Review/Payment: still client-fetch authority (server session required) but shell no longer blank-only; soft reload preserves READY.  
Flight filter/sort: no longer clears READY cards (view change still clears).
