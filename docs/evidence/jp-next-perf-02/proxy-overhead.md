# Proxy overhead

Browser groups inventory before: ~3345ms  
Laravel local: ~826ms  
Implied OLS/public proxy+RTT overhead ≈ 2.5s on that path.  
SSR uses `LARAVEL_URL=http://127.0.0.1:8088` → removes browser hop for primary authority.

OLS_TO_NEXT_OVERHEAD: document TTFB to Next local is small vs public HTTPS RTT; public doc TTFB post-fix ~300–1000ms depending on cold/warm.
