# Database profile — before (code + 02D context)

No server DB spans existed pre-deploy.

Code audit of progressive init path:

- Agency by slug (1)
- Airport IATA ×2 for inline display
- Cache put for `beginSearch`
- Module settings cache

Post-supplier hot path (not pre-network): `MarkupRule` full active set **per offer**.
