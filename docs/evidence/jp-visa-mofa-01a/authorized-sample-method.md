# Authorized sample method

## Result

| Flag | Value |
|---|---|
| AUTHORIZED_SAMPLE_AVAILABLE | **NO** |
| LIVE_SAMPLE_AUTHORIZED | **NO** |
| AUTHORIZED_LOOKUP_ATTEMPTS | `0` |
| AUTHORIZED_LOOKUP_SUCCESS | **NO** |
| PDF_PROTOCOL_CLOSURE | **BLOCKED_AUTHORIZED_SAMPLE_REQUIRED** |
| SENSITIVE_LOOKUP_VALUES_PERSISTED | **NO** |
| CAPTCHA_SOLVER_USED | **NO** |
| CAPTCHA_HUMAN_ENTRY | **N/A** (no lookup attempted) |

## Checks performed (presence only; no values printed)

Environment variables checked (all unset):

- `JP_MOFA_SAMPLE`
- `MOFA_SAMPLE`
- `JP_VISA_SAMPLE`
- `AUTHORIZED_VISA_SAMPLE`

Local file paths checked (all missing):

- `%USERPROFILE%\.jp-mofa-sample.env`
- `%TEMP%\jp-mofa-authorized-sample.env`
- `C:\Users\khadi\ota-jetpk\.local\mofa-authorized-sample.env`
- `C:\Users\khadi\ota-jetpk\tmp\mofa-authorized-sample.env`

Repo search: no MOFA/visa sample artifacts under writable tree.

## Rules obeyed

- Did **not** invent, guess, enumerate, or reuse third-party identities
- Did **not** submit fake passport/visa values to MOFA
- Did **not** open a live lookup session for identity search

## How to unlock PDF protocol closure (future re-run)

Owner supplies legitimate self-authorized lookup values via a **transient local** channel only (never Cursor prompt, Git, evidence, or screenshots), then:

1. Human solves CAPTCHA manually in the same MOFA session
2. One successful lookup (+ at most one CAPTCHA mistype retry)
3. Capture sanitized protocol metadata only
4. Transient PDF SHA256 compare; delete bytes immediately
