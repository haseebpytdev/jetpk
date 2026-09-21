# GitHub rulesets / branch protection evidence

**Date:** 2026-09-21  
**Repo:** `haseebpytdev/jetpk`

## Main

Classic branch protection applied (ruleset PR/status schema rejected by API; classic PUT succeeded):

| Setting | Value |
|---|---|
| Force pushes | blocked (`allow_force_pushes=false`) |
| Deletions | blocked |
| Enforce admins | true |
| Pull request reviews | required (1) |
| Linear history | required |
| Conversation resolution | required |
| Status checks | `release-guards` (strict / branch up to date) |

```
MAIN_PROTECTION=PASS
```

## Checkpoint / backup

Repository ruleset **JetPakistan recovery checkpoints** (`id=23762235`):

- refs: `checkpoint/jetpakistan-app-release-20260921`, `backup/jetpakistan-post-recovery-20260921`
- rules: deletion blocked, non-fast-forward blocked

```
CHECKPOINT_PROTECTION=PASS
```

## Tags

Repository ruleset **JetPakistan recovered-final tags immutable** (`id=23762236`):

- pattern: `refs/tags/jetpakistan-recovered-final-*`
- rules: deletion blocked, update blocked

Existing tag `jetpakistan-recovered-final-20260921` was **not** moved.

```
TAG_PROTECTION=PASS
```

## Open PR #3

- URL: https://github.com/haseebpytdev/jetpk/pull/3
- Head: `docs/iati-capability-reference-20260815`
- Base: `phase/jetpk-owner-uat-wave-2-admin-staff-business-closure`
- State: OPEN draft
- Classification: **KEEP_OPEN** â€” docs/backlog reference; do not delete head branch; not targeting production `main`.

## Notes

- Routine direct pushes to `main` are blocked for administrators as well (`enforce_admins=true`).
- Emergency bypass requires repository-owner procedure outside these automated rules (documented operationally; not encoded as a standing bypass actor).

## Live API re-check

Captured ulesets-live.json / ulesets-live-summary.json via GET /repos/haseebpytdev/jetpk/rulesets:

- id 23762235 JetPakistan recovery checkpoints — enforcement=active target=branch
- id 23762236 JetPakistan recovered-final tags immutable — enforcement=active target=tag

(Initial PowerShell Out-File JSON create attempts returned HTTP 400 due to BOM; successful create used UTF-8 Python client. Classic main protection verified in main-protection-verify.json.)

