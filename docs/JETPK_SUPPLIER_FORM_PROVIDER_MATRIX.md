# JetPK Supplier Form — Provider Field Matrix

Phase: **JETPK-DASHBOARD-FORM-CLEANUP-AND-PROVIDER-SCOPED-FIELDS-9G-R1**

Config source: `config/supplier_credentials.php`  
Form: `resources/views/dashboard/admin/api-settings/form.blade.php` + `partials/supplier-panels/*`

## Connection (all providers)

| Field | Required | Notes |
|-------|----------|-------|
| `provider` | yes | Enum `SupplierProvider` |
| `name` | yes | Unique per agency + provider |
| `status` | yes | active / inactive |

## Sabre (`sabre`)

| Field | Section | Required | Notes |
|-------|---------|----------|-------|
| `environment` | Provider config | yes | sandbox (CERT) / live |
| `base_url` | Provider config | auto | Derived; override in Advanced |
| `credentials.sign_in` | Credentials | yes | EPR / Client ID |
| `credentials.password` | Credentials | yes | Secret; blank retains on edit |
| `credentials.pcc` | Credentials | optional | PCC |
| `sabre_gds_enabled` | Advanced | optional | Default on |
| `sabre_ndc_enabled` | Advanced | optional | Default off |
| `settings_json` | Advanced | optional | `{}` default |

**Hidden from primary UI:** IATI, PIA NDC, AirBlue, Duffel, generic duplicate env/status rows.

## IATI (`iati`)

| Field | Section | Required |
|-------|---------|----------|
| `environment` | Provider config | yes (cert/live) |
| `base_url` | Provider config | auto-derived |
| `credentials.auth_code` | Credentials | yes |
| `credentials.secret` | Credentials | optional (required for flight API) |
| `credentials.organization_id` | Credentials | optional |

## PIA NDC / Hitit (`pia_ndc`)

| Field | Section | Required |
|-------|---------|----------|
| `environment` | Provider config | yes |
| `base_url` | Provider config | yes (SOAP endpoint) |
| `credentials.username` | Credentials | yes |
| `credentials.password` | Credentials | yes |
| `credentials.agency_id` | Credentials | yes |
| `credentials.agency_name` | Credentials | yes |
| `credentials.owner_code` | Credentials | yes |
| `credentials.carrier_code` | Credentials | optional (PK) |
| `credentials.currency` | Credentials | optional |
| `credentials.language_code` | Credentials | optional |
| `credentials.mco_invoice_number` | Credentials | optional |
| `credentials.payment_type` | Credentials | optional |

## AirBlue / Crane NDC (`airblue`)

| Field | Section | Required |
|-------|---------|----------|
| `credentials.api_channel` | Provider config | yes (crane_ndc / zapways_ota) |
| `environment` | Provider config | yes |
| `base_url` | Provider config | yes (auto-filled per channel) |
| Crane fields | Credentials | channel=crane_ndc |
| Zapways fields | Credentials | channel=zapways_ota |

## Duffel (`duffel`)

| Field | Section | Required |
|-------|---------|----------|
| `environment` | Provider config | yes |
| `base_url` | Provider config | optional |
| `credentials.access_token` | Credentials | yes |
| `credentials.api_version` | Credentials | optional (v2) |

## Amadeus / Travelport / Airline direct

| Field | Section | Required |
|-------|---------|----------|
| `environment` | Generic panel | yes |
| `base_url` | Generic panel | optional |
| Provider credentials per `config/supplier_credentials.php` | Credentials | per field meta |

## Not in this form

- **AirSial** — not in `SupplierProvider` enum for this fork.
- **Al-Haider / Group ticketing** — separate group ticketing module, not supplier connection CRUD.

## Advanced-only (all providers)

- `settings_json` — collapsed disclosure
- Sabre: GDS/NDC channel toggles, base URL override

## Storage / validation

Unchanged: `StoreSupplierConnectionRequest`, `UpdateSupplierConnectionRequest`, `SupplierConnectionService`, encrypted credentials.
