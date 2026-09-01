# Sabre empty revalidation

## Policy (preserved from R7C)

Live Sabre RT revalidation that returns empty/unusable is classified as recoverable `search_refresh_required` and recovered via bounded fresh search + deterministic rematch.

## Pattern

| Field | Value |
|---|---|
| SABRE_RT_EMPTY_PATTERN | `sabre_revalidation_empty_or_unusable_response` on some RT offers during live revalidate |
| SABRE_RT_EMPTY_PREDICTABLE | NO (intermittent / offer-specific; not all RT offers) |
| SABRE_RT_EMPTY_REVALIDATION_RATE | intermittent — not 100%; successful RT revalidates observed in R7D (~2s) |

Do not disable Sabre revalidation globally. Do not bypass authoritative validation for speed.
