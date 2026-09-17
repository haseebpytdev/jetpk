# JetPakistan Release Integrity Policy

**Status:** DRAFT — activate after product P0 closed and first certified release of this reconciliation.

## Exact SHA parity

```
Git main SHA
= release SHA
= production runtime SHA
= successful Next build source SHA
```

No production-only patches. Hotfix: fix locally → test → commit → push → deploy exact SHA.

## Lifecycle statuses

| Status | Meaning |
|---|---|
| CODED | Merged/committed engineering SHA |
| DEPLOYED | Exact SHA live with backup/rollback evidence |
| LIVE VERIFIED | Post-deploy UAT matrices pass |

Never call CODED “Done”.

## Mandatory gates

- Critical regression: OTP OFF/ON, Destinations section, PairReturnCard, Segmented flow, Ask FAB, Details, branded fares, favicon, auth OTP admin gate
- Network/console clean on smoke routes
- SEO regression gate
- Commercially safe UAT: no supplier/payment mutations unless explicitly approved QA hold
- Unbiased performance harnesses (no threshold retries)
- Preserve `frontend/.env.production.local`
- No Next restart before successful build + valid `.next`

## Branch / history

- Historical diverged branches = reference only
- No blind cherry-pick / force push
- Record RELEASE_SHA, ROLLBACK_SHA, BUILD_ID, RUNTIME_MARKER

## Machine enforcement (to implement)

- CI check: critical feature-presence tests
- Deploy script: refuse if NEXT_BUILD_SOURCE_SHA ≠ RELEASE_SHA
- Pre-deploy audits: fail>0 / server_errors>0 / mojibake / new production.ERROR block upload
