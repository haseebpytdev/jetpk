# Network trace (sanitized)

## One Way

- `GET /laravel/flights/results/search` → 200  
- `GET /laravel/flights/results/data?search_id=…` → 200  
- `POST /laravel/flights/results/revalidate-offer` → 200 success → `/booking/passengers`

## Return Paired

- Search + data polls complete; pairs render  
- Pre-fix revalidate → 422 failed (empty Sabre live response)  
- Post-fix revalidate → 200 success after search rematch → Traveler  

RETURN_PENDING_REQUEST_IDENTIFIED=NO (after fix)  
RETURN_STALE_RESPONSE_RACE_IDENTIFIED=YES historically (requestSeq); guarded now  

No secret headers captured.
