# TLS / mTLS — Zapways TEST

**Expected cert:** `/home/pkjetp/jetpk_secrets/zapways/jetpakistan-zapways.crt.pem`  
**Expected key:** `/home/pkjetp/jetpk_secrets/zapways/jetpakistan-zapways.key.pem`

## File safety (no private key material read)

| Check | Result |
|---|---|
| TLS_CERT_EXISTS | YES |
| TLS_KEY_EXISTS | YES |
| TLS_CERT_READABLE (runtime `nobody` after fix) | YES |
| TLS_KEY_READABLE (runtime `nobody` after fix) | YES |
| TLS_PERMISSIONS_SAFE | YES (after corrective `750` dir + `640` key, group `nogroup`) |
| TLS_PATH_CONFIG | PASS (env paths match expected files) |

**Pre-fix defect:** `jetpk_secrets/zapways` was `700` root-only; OpenLiteSpeed PHP (`nobody`) could not read client cert/key → application transport failed (`supplier_transport_failed`). Corrective filesystem permissions applied on production (ops, not app code).

## OpenSSL mTLS probe (transport only)

- **Target:** `otatest4.zapways.com:443` (SNI `otatest4.zapways.com`)
- **TLS:** PASS (`TLSv1.3`, peer `*.zapways.com`, verification OK)
- **CONNECTIVITY:** PASS

No credentials or key contents recorded.
