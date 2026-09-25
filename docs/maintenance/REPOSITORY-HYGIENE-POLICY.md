# JetPakistan Repository and Production Hygiene Policy

**Status:** mandatory  
**Applies to:** all JetPakistan development, testing, recovery, deployment, and AI-agent work  
**Canonical local workspace:** `C:\Users\khadi\ota-jetpk`  
**Agent entry points:** `AGENTS.md` (hard rules + this link), `CLAUDE.md` (must not conflict)

This policy is permanent architecture hygiene. Clean does **not** mean empty.

Objectives:

- minimum justified architecture
- zero unexplained pollution
- zero loss of current source
- zero loss of production data
- zero loss of required recovery capability

When cleanliness conflicts with uncertain current work: **PRESERVATION WINS** — mark `HOLD` until verified.

---

## 1. Canonical local workspace

The **only** normal JetPakistan workspace is:

```text
C:\Users\khadi\ota-jetpk
```

Agents **must not** create sibling JetPakistan folders such as:

- `ota-jetpk-*`
- `jetpk-*`
- `ota-jetpk-audits`
- temporary clones / temporary repositories
- temporary worktrees
- external deployment staging directories

unless the owner **explicitly** authorizes an exceptional recovery operation.

Normal work stays inside the canonical project.

### Approved temporary locations (ignored where appropriate)

| Location | Purpose |
|----------|---------|
| `.recovery/` | Recovery / rollback evidence only |
| `docs/maintenance/local-audits/` | Temporary local audit reports (ignored; README may be tracked) |
| `storage/` | Laravel/runtime-controlled writable data |
| `tests/` | Test source |
| `tests/e2e/` | Browser / E2E source |

Generated output must **not** create arbitrary root directories.

---

## 2. Top-level architecture contract

Top-level directories must map to a **current** architectural component:

| Component | Typical owners |
|-----------|----------------|
| Laravel backend / application engine | `app/`, `bootstrap/`, `config/`, `database/`, `public/`, `resources/`, `routes/`, `storage/` |
| Next.js public frontend | `frontend/` |
| Next.js dashboard / portal | `dashboard/` |
| AI support / runtime | `ai-lab-gateway/`, `ai-assistant/` |
| Tests / QA | `tests/` |
| Deployment / operations | `deploy/`, `scripts/`, related tooling |
| Documentation | `docs/` |
| Project tooling | `tools/`, `.cursor/`, `.github/`, etc. |

Do **not** introduce permanent root directories for:

temporary phases · downloaded UI kits · legacy themes · template references · one-off tools · test screenshots · browser artifacts · package staging · deployment ZIPs · audit output · debug dumps · copied dependencies · migration scratch · historical experiments

### Before adding a new top-level directory

1. Prove an existing architectural owner is unsuitable.
2. Document why it must be top-level.
3. Ensure it is current product/engineering architecture, not generated output.

### Default placement

| Material | Destination |
|----------|-------------|
| App code | Owning application / service |
| Tests | `tests/` |
| Utilities | `tools/` or `scripts/` |
| Operations | `deploy/` |
| Documentation | `docs/` |
| Recovery | `.recovery/` |
| Temporary generated output | Ignored and disposable |

---

## 3. Legacy code rule

Git history is the historical archive.

Do **not** keep retired code in the current tree merely “in case.”

When a feature / theme / system is conclusively retired and no current runtime, build, test, recovery, or deployment path requires it: remove it through normal Git history.

Do **not** create in-tree archives such as:

`legacy/` · `old/` · `backup-copy/` · `previous-theme/` · `templates-source/` · `archive-old-ui/`

### Before removal

Search current references in:

- application code
- build tooling
- CI
- deployment
- tests
- recovery procedures

**UNKNOWN = KEEP / HOLD** until resolved.

---

## 4. Frontend authority

Canonical user interfaces:

- `frontend/` → Next.js public frontend
- `dashboard/` → Next.js dashboard / portal

Laravel frontend resources may remain **only** when currently required by:

- Laravel Blade runtime
- email rendering
- error / fallback pages
- operational / backend views
- active Vite pipeline
- other verified current functionality

Do not retain historical theme / template assets superseded by the canonical Next applications.

---

## 5. Dependency rule

Allowed local dependency trees (generated from lockfiles, ignored, never committed):

- `vendor/`
- `node_modules/` (root, if Laravel Vite pipeline requires it)
- `frontend/node_modules/`
- `dashboard/node_modules/`

Rules:

- Install from existing lockfiles unless dependency change is explicitly in scope.
- Do **not** use dependency upgrades as part of cleanup.
- Do **not** manually archive dependency trees inside the repository.

---

## 6. Generated output rule

Must remain disposable and untracked:

`.next/` · `coverage/` · `test-results/` · Playwright screenshots / videos / traces · temporary archives · release ZIP/TAR · package staging · local audit output · browser / npm / tool caches · temporary build directories

Never force-add ignored generated material.

If a tool repeatedly creates a new root artifact: fix the tool output path or `.gitignore` — do not accept repository pollution.

---

## 7. Playwright / test hygiene

- Canonical test root: `tests/`
- Canonical browser / E2E hierarchy: `tests/e2e/`
- Playwright configs: prefer `tests/e2e/playwright/` (configs / specs / fixtures / helpers)
- At most one simple root Playwright entrypoint if tooling requires it
- Do not create new root Playwright configs unless the canonical structure cannot support the requirement
- Generated browser evidence must never become permanent repository-root files

---

## 8. Tooling rule

Reusable utilities live under `tools/` or `scripts/`.

One-off tools (data conversion, temporary upload, audio experiments, icon extraction, migration helpers, one-time QA helpers) must **not** become permanent standalone root applications.

- Reusable → `tools/` or `scripts/`
- Temporary → remove when the task closes

---

## 9. Audit / evidence rule

- Do **not** create external audit directories.
- Temporary local reports: `docs/maintenance/local-audits/` (ignored).
- Recovery evidence: `.recovery/` only when actual recovery / rollback evidence is required.

After work is certified:

- remove disposable audit evidence
- remove duplicate recovery evidence
- retain only what is explicitly needed for rollback / recovery / compliance

Do not accumulate evidence forever. Prove equivalence / reference status, then clean it.

---

## 10. Worktree rule

**Default:** do **not** create additional Git worktrees for routine work. Use normal branches inside the canonical workspace.

A worktree may be created only when technically necessary for an exceptional operation and must record:

`PURPOSE` · `BRANCH` · `OWNER` · `CREATED_DATE` · `RETIREMENT_CONDITION`

No worktree may remain indefinitely.

Before retiring:

1. Preserve unique commits.
2. Preserve required untracked source.
3. Verify remote / checkpoint refs.
4. Ensure no deployment / build process references its path.
5. Use Git-aware `git worktree remove` (never raw-delete registered worktrees).

Target normal state:

```text
ACTIVE_EXTERNAL_WORKTREES=0
ACTIVE_NESTED_TEMP_WORKTREES=0
```

---

## 11. Git cleanliness gate

Before committing or pushing, check:

```bash
git status
git diff --check
```

Confirm nothing accidental is staged:

`.env` · credentials · private keys · backups · `node_modules` · `vendor` · `.next` · test output · traces / videos / screenshots · release archives · temporary packages · audit dumps

Do not force-add to bypass hygiene without explicit technical justification.

Do not rewrite Git history merely to save disk space.

---

## 12. Secret / config rule

Never commit:

`.env` · `.env` backups with real credentials · private keys · tokens · passwords · production secrets

`.env.example` may contain safe placeholders only.

Certificate / CSR / public certificate files need a documented operational reason before remaining tracked.

Private key material must **never** be stored in Git.

Before pushing unusual config / security files: secret-scan without printing secret values.

---

## 13. Local cleanup safety

**Never** perform broad destructive cleanup such as:

- `git clean -fdx`
- `git reset --hard`
- bulk wildcard deletion

merely to make the repository look clean.

Before deleting local material prove:

`TRACKED?` · `IGNORED?` · `CURRENT_REFERENCE?` · `BUILD_REFERENCE?` · `TEST_REFERENCE?` · `DEPLOY_REFERENCE?` · `RECOVERY_REFERENCE?` · `UNIQUE_WORK?`

**UNKNOWN = HOLD.**

---

## 14. Production architecture rule

Production cleanup is **independent** from local cleanup.

Never assume a local path maps directly to a production path.

Before production cleanup identify:

`ACTIVE_RUNTIME` · `ACTIVE_SHA` · `DEPLOY_MARKER` · `ROLLBACK_REFERENCE` · `PERSISTENT_STORAGE` · `CURRENT_PROCESSES`

Never delete:

`.env` · persistent uploads · database files / data · active application source · active dependencies · current build output · current AI runtime · current deployment marker · required recovery / rollback material

---

## 15. Production generated-data rule

Cache / generated material may be cleaned only when proven regeneratable (application cache, flight-search cache, npm / Composer / pip caches, old package extraction, temporary deployment staging).

Before purge:

1. Identify owner / process.
2. Verify regeneratability.
3. Capture size / permissions where required.
4. Ensure current process is not using it.
5. Define rollback where appropriate.

Prefer: **rotate → recreate → validate → delete old copy**.

---

## 16. Deployment cleanup rule

Every successful deployment should conclude with a hygiene check.

Deployment tooling must not indefinitely accumulate unpacked staging, temporary archives, old build dirs, tool caches, or temporary test evidence.

Automated cleanup must **never** delete active or required rollback material.

Before deleting an old release / package establish:

`NOT ACTIVE` · `NOT ROLLBACK TARGET` · `NOT PROCESS-REFERENCED` · `NOT DEPLOY-SCRIPT-REFERENCED` · `NOT RECOVERY-REQUIRED`

---

## 17. Server backup rule

Backups are protected data.

Do not clean production backups simply because they consume space.

Until an explicit retention policy defines retention period, minimum recovery points, offsite status, and verified restore capability:

```text
BACKUPS = PROTECTED
```

---

## 18. Server disk monitoring

Deployment / maintenance checks should record:

disk free · application directory size · cache size · log size · deployment staging size

Unexpected growth must be traced to its owner rather than blindly deleted.

Classifications: `APPLICATION_SOURCE` · `DEPENDENCIES` · `PERSISTENT_DATA` · `CACHE` · `LOGS` · `DEPLOYMENT_ARTIFACTS` · `BACKUPS` · `UNKNOWN`

**UNKNOWN = do not delete.**

---

## 19. Source vs performance

Do not claim repository cleanup improves application speed by itself.

Treat separately:

repository / source hygiene · disk / storage hygiene · runtime performance · frontend bundle optimization · HTTP compression · database / query performance · application caching

Performance changes require their own evidence and regression testing.

---

## 20. Post-task cleanup gate

Every substantial Cursor / Codex task must end with a hygiene check.

Before declaring the task complete, report:

```text
NEW_TOP_LEVEL_DIRECTORIES=
NEW_GENERATED_ARTIFACTS=
NEW_UNTRACKED_FILES=
NEW_EXTERNAL_WORKTREES=
NEW_TEMP_DIRECTORIES=
NEW_DEPLOYMENT_ARTIFACTS=
```

Expected normal result:

```text
NEW_EXTERNAL_WORKTREES=0
UNEXPLAINED_TOP_LEVEL_ITEMS=0
UNEXPLAINED_GENERATED_ARTIFACTS=0
```

If temporary task material was created: remove it before closure once it is no longer required.

---

## 21. Deployment postcheck

After every production deployment verify:

`DEPLOYED_SHA` · `DEPLOY_MARKER` · HTTP smoke · critical application health · disk before / after · orphan staging / packages

Do not run destructive cleanup if health checks fail.

Cleanup follows successful validation; it never precedes it blindly.

---

## 22. Mandatory safety principle

```text
CLEAN DOES NOT MEAN EMPTY.
```

When cleanliness conflicts with preservation of uncertain current work:

**PRESERVATION WINS** — mark `HOLD` until verified.

---

## Related documents

- `docs/PRODUCTION_DEPLOYMENT_SAFETY.md`
- `docs/jetpk/DEPLOYMENT-CONTEXT.md`
- `docs/maintenance/REPOSITORY-TOPOLOGY-2026-09-24.md`
- `docs/maintenance/local-audits/README.md`
- `AGENTS.md`
- `CLAUDE.md`
