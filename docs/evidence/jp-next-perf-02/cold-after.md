# JP-NEXT-PERF-02 — Cold after

Build: `AJ9bvi6_QyxDfAP2TgofV`  
Runtime engineering: `98f92ea9da7017feb99b108267cf174f2b89c896`

## Groups

- Coldish useful card ≈ **2425ms** (logo start; cards in SSR HTML)
- No client inventory/facets refetch when SSR key matches
- Local Next SSR page total **656ms** with FLY JINNAH + View details in HTML

## Compression / chunks

Browser encodedBodySize < decodedBodySize for JS/CSS (compression active to browsers).  
Static assets: `cache-control: public, max-age=31536000, immutable`.  
HTML: `private, no-store`.

## NEXT_RUNTIME_MODE

PRODUCTION (`next start` via PM2 `jetpk-public-frontend`)
