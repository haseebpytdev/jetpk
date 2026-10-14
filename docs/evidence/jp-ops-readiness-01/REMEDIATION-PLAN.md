# JP-OPS-READINESS-01 — Remediation Plan

**Start remediation only after baseline audit summary acceptance (this document).**  
Mode remains: no unauthorized production mutations.

## Wave 1 — P0 watch / immediate ops safety

1. Triage/clear or quarantine `failed_jobs` (147) with root-cause classes — OPS-DEF-0015  
2. Free disk below deploy-safe threshold (<80% target) — OPS-DEF-0014  
3. Document AbhiPay replay/idempotency residual risk closure criteria — OPS-RISK-0002 / OPS-DEF-0013  

## Wave 2 — P1 operational blockers

1. Decide SHA strategy: keep runtime `12045dda` as cert baseline vs protected deploy of `daa5a1a` — OPS-DEF-0001  
2. Fix wallet↔ledger atomicity (`safeRecord` after commit) — OPS-DEF-0006  
3. Detect/cleanup duplicate agency wallets + enforce singular operational wallet — OPS-DEF-0007  
4. Supplier/payment activation plan (business decision) without PIA unfreeze — OPS-DEF-0016/0017  

## Wave 3 — Financial/data integrity

1. DB unique constraint for gateway payment references — OPS-DEF-0013  
2. Live read-only orphan/integrity SQL sampling — DATA-INTEGRITY-AUDIT  
3. Pricing invariant fixture proofs across search→checkout→ledger  

## Wave 4 — Admin/configuration/go-live

1. Complete ADMIN-ACTIVATION-CHECKLIST items  
2. Refresh rollback docs to current runtime SHA — OPS-DEF-0003  
3. Configure monitoring for disk + failed_jobs + mail failures  

## Wave 5 — P2

1. Viewer cannot cancel — OPS-DEF-0004  
2. Guest lookup AND-match when both identifiers provided — OPS-DEF-0005  
3. Deposits BFF agent_id filter hardening — OPS-DEF-0008  
4. Guest token rotation/URL exposure hardening — OPS-DEF-0011/0012  

## Wave 6 — P3 / polish

1. Latent BookingPolicy permission gaps — OPS-DEF-0009/0010  
2. Deep public/portal control matrix completion (currently inventoried)  
3. Usability/a11y deep pass beyond FINAL-11 responsive reference  

## Next recommended wave

**Wave 1** (ops safety: disk + failed_jobs) — can proceed without product code deploy if ops cleanup authorized.
