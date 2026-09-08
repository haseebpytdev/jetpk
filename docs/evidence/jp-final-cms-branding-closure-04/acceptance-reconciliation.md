# Closure-04 acceptance reconciliation (production)

**Authorized production SHA:** `20e921661da55e121a9b2353cba535b350613493`  
**Branch:** `phase/jp-master-unfinished-closure-10`  
**Reconciled:** 2026-09-08T13:10:00Z  
**Overall Closure-04:** **PARTIAL — TEMPORARILY_BLOCKED**

Production engineering SHA unchanged. No application redeploy.

---

## Mandatory gates

| Gate | Status | Evidence |
|------|--------|----------|
| CMS_ADMIN_AUTH | **PASS** | Vault→prod password sync; `HASH_OK=yes`; API login 200 |
| CMS_2250KB_DRAFT_UPLOAD | **FAIL** | Web PHP `upload_max_filesize=2M`; 2048KB=200, 2250KB=422 (`cms-upload-size-probe-results.md`) |
| CMS_ASSET_RECORD | **FAIL** | Blocked on upload |
| CMS_MEDIA_URL_HTTP_200 | **FAIL** | Blocked on upload |
| CMS_DRAFT_PREVIEW | **PARTIAL** | Preview session 200; image fetch blocked (no asset) |
| TEST_FIXTURE_NOT_PUBLISHED | **PASS** | No publish action; marker absent from public homepage |
| CMS_TEST_FIXTURE_CLEANUP | **FAIL** | No asset created |
| Ask 20-turn + dup/429/privacy/Roman Urdu | **PASS** | `closure-04-prod-gates.json` |
| Trending route CTA | **PASS** | `prod-homepage-snapshot.json` |
| Favicon / branding / FAB / curl | **PASS** | Same JSON + `prod-curl-probes.txt` |
| Screenshots | **PARTIAL** | Font-load timeout documented; `prod-gate-ask-open-mobile.png` when available |
| Host connectivity (post PHP reload) | **BLOCKED** | SSH/HTTPS timeout after `lswsctrl stop/start` (`prod-connectivity-incident-20260908.md`) |

---

## Owner actions to reach PASS

1. **Restore production host** (LiteSpeed/SSH) if still down.
2. **Raise web-effective `upload_max_filesize` to ≥6M** (global ini + LSAPI/vhost reload; see `prod-php-upload-limit-adjustment.md`).
3. Re-run:
   ```bash
   node docs/evidence/jp-final-cms-branding-closure-04/run-closure-04-prod-gates.mjs
   ```
4. Expect all `CMS_*` gates **PASS** and `FINAL_CLOSURE_04_PROD_GATES=PASS`.

---

## Evidence index

| File | Purpose |
|------|---------|
| `closure-04-prod-gates.json` | Latest gate machine output |
| `cms-upload-evidence.json` | CMS step log |
| `cms-upload-size-probe-results.md` | 2M ceiling proof |
| `prod-php-upload-limit-adjustment.md` | PHP ini change attempt |
| `prod-connectivity-incident-20260908.md` | Post-restart connectivity |
| `run-closure-04-prod-gates.mjs` | Re-runnable verifier (multipart upload fix included) |

**Closure-04 PASS** is **not** declared until CMS 2.25MB production upload passes and host connectivity is restored.
