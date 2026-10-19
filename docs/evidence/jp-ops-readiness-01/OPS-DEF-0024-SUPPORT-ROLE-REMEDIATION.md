# OPS-DEF-0024 — Support / QA permission remediation

## Code contract (this loop)

| Surface | Expected |
|---------|----------|
| Agency `SupportStaff` template | BookingsView, CancellationsRequest, SupportManage, AgencyView only |
| Platform `PresetSupport` | BookingsView + SupportView/Reply/Status only |
| Wallet / ledger / staff manage / bookings create | **NO** on support templates |
| Payment verify (platform staff) | Requires `staff.payments.verify` — not in support preset |
| Supplier book / ticket | **NO** on support templates |

Automated contract: `tests/Unit/Support/Agencies/SupportStaffPermissionContractTest.php`.

## Production data action (NOT executed this loop)

Legacy QA account `jp-dash-03-qa-agent-staff` (identity redacted in evidence) was observed with over-permissions vs SupportStaff template.

**Required production reconciliation (separate authorized pass):**

1. Prefer FINAL-11 support QA identity for support UAT.
2. For the legacy account: reset `meta.agent_permissions` to `AgencyRolePermissionMatrix::suggestedPermissions(AgencyRole::SupportStaff)`.
3. Do not hardcode user IDs in application code.
4. Re-run WAVE-A authorization matrix probe after reconciliation.

```text
OPS_DEF_0024_CODE_FIX=CONTRACT_TESTS_AND_TEMPLATES_VERIFIED
OPS_DEF_0024_PRODUCTION_DATA_ACTION_REQUIRED=YES
SUPPORT_CAN_BOOK_SUPPLIER=NO
SUPPORT_CAN_MUTATE_WALLET=NO
```
