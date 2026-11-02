# OneAPI Credential Mapping

DATE: 2026-11-02  
BRANCH: `feat/jp-oneapi-e2e-certification-01`

## Two identities (do not conflate)

| Identity | Fields | Used for |
|----------|--------|----------|
| REST / Aero | `rest_username`, `rest_password` | REST authenticate payload (`login`/`password`), search header `X-AERO-USERID` |
| SOAP / AAResWebServices | `soap_username`, `soap_password` | WS-Security UsernameToken; OTA `RequestorID/@ID` on price |

## Shared / independent

| Field | Notes |
|-------|-------|
| `agent_code` | Independent; also `X-AERO-AGENT-CODE` |
| `agent_preferred_currency` | Owner enters locally |
| `pos_country` / `pos_station` | Owner enters locally |
| `sales_channel` | Default OTA; `X-AERO-SALES-CHANNEL` |
| `terminal_id` | Optional |
| `rest_auth_url` | Vendor UAT auth endpoint |
| `rest_search_url` | Vendor UAT search endpoint |
| `soap_url` | Vendor UAT SOAP endpoint |

## Legacy fallback (no migration required)

```
rest_username = credentials.rest_username ?: credentials.username
rest_password = credentials.rest_password ?: credentials.password
soap_username = credentials.soap_username ?: credentials.username
soap_password = credentials.soap_password ?: credentials.password
```

## Owner local entry (never commit)

```
rest_username=<owner enters locally>
rest_password=<owner enters locally>
soap_username=<owner enters locally>
soap_password=<owner enters locally>
agent_code=<owner enters locally>
rest_auth_url=<vendor supplied UAT endpoint>
rest_search_url=<vendor supplied UAT endpoint>
soap_url=<vendor supplied UAT endpoint>
agent_preferred_currency=<vendor/account setting>
pos_country=<vendor/account setting>
pos_station=<vendor/account setting>
```

Blank password on edit retains existing encrypted value via `OneApiSupplierConnectionNormalizer`.
