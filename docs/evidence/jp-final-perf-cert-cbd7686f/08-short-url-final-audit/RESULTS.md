# Short URL final audit — `cbd7686f`

| Check | Result |
|---|---|
| Default opaque land (`short_url` from init) | PASS |
| Refresh keeps `/flights/s/{ref}` | PASS |
| Back/forward | PASS |
| Expiry UI (no search_id leak) | PASS |
| Legacy `/flights/results?search_id=` | PASS |
| OLS ownership Next | PASS (`scripts/jp-ols-assert-flights-short-url.sh`) |
| Snippet reproducible | PASS (`deploy/openlitespeed/jetpakistan-vhost-routes.conf`) |

```
SENSITIVE_DATA_IN_URLS=0
PII_IN_URLS=0
LONG_SERIALIZED_STATE_URLS=0
OPEN_REDIRECTS=0
SHORT_URL_GUARD=PASS
```

PII inventory: `docs/closure/SEO-AEO-GEO/08-url-inventory-pii-audit.md`
