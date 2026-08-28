# Failure / root causes — JP-FINAL-CLOSURE-01

## Fixed this run

### Checkout/registration HTTP 500 on SMTP failure

- **Symptom:** Inline checkout `create_account` returned HTTP 500; Draft/user rolled back.
- **Root cause:** `event(Registered)` inside `DB::transaction` + Laravel stock `SendEmailVerificationNotification` threw on SMTP error.
- **Fix:** Move Registered after commit; replace stock listener with `SendEmailVerificationNotificationBestEffort` (forget+rebind at boot); graceful session/JSON messaging; failing-mail transport tests.
- **Deployed:** `fa6dfdc4` — LIVE_SOURCE_DRIFT=0, OLS=PASS.

## Open

1. **NEW_QA_CUSTOMER_E2E** — sink built (`9200165a`) but not enabled/deployed; no live signed verification proof.
2. **EMAIL_SYSTEM** — inventory complete; visual/standardization incomplete.
3. **GROUPS_HERO** — engineering on branch (`7d4302c7`) not in mail deploy manifest.
4. **Portal/flight full cert** — not re-executed in this loop (R3 closed subset remains historical).
5. **Sabre cancel env flags** — observed true; external owner decision; not a code defect from this run.
