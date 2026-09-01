# Logs root cause (sanitized)

RETURN_SEARCH_LOG_ERROR=none after poll restart fix  
FARE_CONTINUE_LOG_ERROR=`sabre_revalidation_empty_or_unusable_response` (pre-fix)  
SUPPLIER_TIMEOUT_FOUND=NO (empty/unusable response, not timeout)  
APPLICATION_TIMEOUT_FOUND=client deadline present for empty RT  
CLIENT_TIMEOUT_FOUND=YES (60s/90s guards)  
STALE_STATE_FOUND=YES (requestSeq abort without reschedule — fixed)  
CACHE_COLLISION_FOUND=NO  
ROOT_CAUSE_CONFIDENCE=HIGH  

No raw private logs committed.
