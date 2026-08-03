# JP-OPS-05 Support Report Audit Contract

## Support

| Operation | Status |
|-----------|--------|
| List/detail read | Deferred Next (Blade operational) |
| Reply/status/assign JSON | Deferred — portal controllers retain Blade |

Internal notes must never serialize to Customer/Agent portals.

## Reports

- Connected reads: `/api/dashboard/reports/*`
- Export: Blade GET exports retained; CSV formula-injection rules apply when connected

## Audit logs

- Connected reads: `/api/dashboard/audit`
- Immutable through dashboard
- Redaction via `AuditFieldMasker` / dashboard audit resources
- No passwords, OTP, card data, credentials, raw storage paths
