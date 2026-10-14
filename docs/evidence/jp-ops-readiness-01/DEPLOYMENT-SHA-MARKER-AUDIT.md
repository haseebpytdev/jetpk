# Deployment SHA Marker Audit — JP-OPS-INFRA-DISK-RETENTION-01

**Date:** 2026-10-14 UTC  
**Canonical runtime:** `a138eb570b05910046df7fb251beeef839b25395`

## Markers inspected

| Path | Pre value | Post value | Classification |
|------|-----------|------------|----------------|
| `/home/pkjetp/jetpk_app/.jetpk-runtime-sha` | a138eb57… | a138eb57… | **AUTHORITATIVE** |
| `/home/pkjetp/jetpk_app/.jetpk-authorized-sha` | a138eb57… (aligned) | a138eb57… | AUTHORITATIVE (deploy guard) |
| `/home/pkjetp/jetpk_app/storage/app/deploy-sha.txt` | ab26661f… | **a138eb57…** | Was **STALE_BUT_REFERENCED**; reconciled |
| `storage/app/AUTHORIZED_DEPLOY_SHA` | missing | missing | UNUSED |
| `public_html/.jetpk-runtime-sha` | missing | missing | UNUSED |
| `dashboard/.next/BUILD_ID` | 7-iZf4RbaJmGpuDjO1tsh | unchanged | FRONTEND build marker |

## Who reads what

- **Authoritative runtime identity for ops probes / AI deploy guards:** `.jetpk-runtime-sha` (`scripts/jetpk/deploy-ai-runtime-protected.sh`, `guard-authorized-sha.sh`, Wave evidence probes).
- **`deploy-sha.txt`:** Written by `scripts/jetpk/write-deploy-sha-marker.sh` / `finalize-deploy-sha-marker.sh` after successful canonical deploy. Historical dashboard/cert reports treat it as parity companion to `.jetpk-runtime-sha`. Not read by Laravel request path under `app/` (no PHP runtime dependency found).
- Wave1C finish script updated `.jetpk-runtime-sha` but left `deploy-sha.txt` stale → root of observed drift.

## Action taken

```
DEPLOY_SHA_MARKER_PRE=ab26661f0107d9bd8f9889dda1d59567d93be683
DEPLOY_SHA_MARKER_POST=a138eb570b05910046df7fb251beeef839b25395
METHOD=atomic write printf+mv (same semantics as write-deploy-sha-marker.sh)
```

## Final

```
RUNTIME_SHA_MARKERS_CONSISTENT=YES
RUNTIME_SHA_MARKERS_RECONCILED=YES
```
