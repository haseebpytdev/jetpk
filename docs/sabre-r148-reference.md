# Sabre — R148 (production reference)

Internal operational notes for the entitled production Sabre connection. **Do not commit passwords or rotate credentials into version control.** Keep secrets in **Admin → Supplier API settings** (encrypted storage) and in `.env` only where appropriate.

| Field | Value |
|--------|--------|
| Environment | production |
| Base URL | `https://api.platform.sabre.com` (non-prod: e.g. `https://api.cert.platform.com` or the host Sabre provides for your cert/sandbox account) |
| Token endpoint | `/v2/auth/token` |
| Shop endpoint (BFM / Offers Shop) | `/v4/offers/shop` (see `suppliers.sabre.shop_path` / `SABRE_SHOP_PATH`) |
| PCC | R148 |
| Sign in / EPR | 487390 |
| Domain | AA |
| Password | *(set only in Admin supplier credentials or secure vault — never paste here)* |
| Auth strategy (app) | `sabre_epr_encoded` |

For URL construction in this project: base URL + token path for OAuth; base URL + configured shop path for search (`SabreClient`).
