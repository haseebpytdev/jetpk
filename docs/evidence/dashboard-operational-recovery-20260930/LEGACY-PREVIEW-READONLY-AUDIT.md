# LEGACY PREVIEW / READ-ONLY AUDIT — Batch 1.5

```text
BRANCH=work/jetpk-dashboard-operational-recovery-20260930
SCOPE=dashboard/
DATE=2026-09-30
```

| PATH | SYMBOL/TEXT | CURRENT_USE | PRODUCTION_VISIBLE | CORRECT_CLASSIFICATION | ACTION | TEST | STATUS |
|---|---|---|---|---|---|---|---|
| `dashboard/types/read-only-integration.ts` | `DATA_SOURCE_MODES` / `READ_ONLY_SCHEMA_VERSION=dash-read-only-v1` | Transport/metadata schema; schema version name retained | No (types only) | LIVE_OPERATIONAL (transport) | Reframed comment; keep version string for envelope compat | foundation | DONE |
| `dashboard/lib/read-only/data-source.ts` | `resolveDataSourceMode` | live+mock-off → `laravelLive` | Indirect | LIVE_OPERATIONAL | Fixed; `laravelReadOnly` only for non-live Laravel GET | foundation | DONE |
| `dashboard/lib/preview.ts` | `getDashboardMode` / `mutationsAllowed` | live\|production → live; live mutations default on | Indirect (build-time) | LIVE_OPERATIONAL | Keep; `.env.example` MODE=live ALLOW_MUTATIONS=true | foundation | DONE |
| `dashboard/components/dashboard/data-source-notice.tsx` | `DataSourceNotice` | No banner on `laravelLive` | Yes if mis-mode | LIVE_OPERATIONAL | Fixed — live silent; RO notice only for `laravelReadOnly` | foundation | DONE |
| `dashboard/components/ui/data-source-status.tsx` | `LiveReadOnlyNotice` | Intentional RO copy | Only when mode=`laravelReadOnly` or preview stack | VALID_READ_ONLY_BY_DESIGN | Removed phase wording; title=Read-only operational view | foundation | DONE |
| `dashboard/components/ui/data-source-status.tsx` | `FixtureDataNotice` / `AccessControlPreviewNotice` | Fixture/preview notices | Only fixture mode | TEST/FIXTURE_ONLY | Keep gated by mode | smoke | DONE |
| `dashboard/components/ui/data-source-status.tsx` | `DataSourcePreviewStack` case live | Dev preview stack | Opt-in `dataSourcePreview` | TEST/FIXTURE_ONLY | live → `LiveOperationalNotice`; readOnly → RO notice | foundation | DONE |
| `dashboard/components/dashboard/header.tsx` | mockUser fallback | Preview identity when session missing | Was YES (bug) | OBSOLETE_PRODUCTION_SCAFFOLD | Live: Session unavailable only; never Preview Admin | headed UAT | DONE |
| `dashboard/components/dashboard/sidebar.tsx` | Preview user / Session unavailable | Fallback labels | Live shows unavailable only | LIVE_OPERATIONAL | Keep live honest unavailable | headed UAT | DONE |
| `dashboard/services/session-service.ts` | `getDashboardSession` / fixture / unavailable | Laravel `/api/dashboard/session` via `dashboardApiUrl` | Yes | LIVE_OPERATIONAL | Adapter `laravelLive`; live catch → unavailable (no preview.user) | foundation+UAT | DONE |
| `dashboard/features/profile/profile-page-content.tsx` | former `/api/dashboard/session` | Shell session + `getDashboardSession` | Yes | LIVE_OPERATIONAL | No Next BFF; fail closed in live; no preview.user | headed UAT | DONE |
| `dashboard/app/layout.tsx` | SSR `getDashboardSession` | Seeds SessionProvider | Yes | LIVE_OPERATIONAL | Keep Laravel session only | UAT | DONE |
| `dashboard/app/api/**` | Next session route | Absent (correct) | N/A | OBSOLETE_PRODUCTION_SCAFFOLD | Do not add Next `/api/dashboard/session` | audit | DONE |
| `dashboard/services/*-service.ts` laravel adapters | `mode: laravelLive` | Adapter stamp | No | LIVE_OPERATIONAL | Bulk reframe from laravelReadOnly | foundation | DONE |
| `dashboard/services/cms-service.ts` | CMS unavailable message | Module unavailable text | Rare | LIVE_OPERATIONAL | Renamed away from Laravel read-only | n/a | DONE |
| `dashboard/lib/read-only/laravel/transformers/overview.ts` | systemHealth name | Overview health chip | Yes | LIVE_OPERATIONAL | "Live Laravel data" | smoke | DONE |
| `dashboard/features/*/…-workspace.tsx` empty states | synthetic preview data | Empty list body | Yes when empty | OBSOLETE_PRODUCTION_SCAFFOLD | `emptyListDescription(isLive)` | foundation | DONE |
| `dashboard/lib/empty-list-copy.ts` | emptyListDescription | Shared copy helper | Indirect | LIVE_OPERATIONAL | New | foundation | DONE |
| `dashboard/components/dashboard/overview-toolbar.tsx` | Export preview alert | Toolbar | Yes | OBSOLETE_PRODUCTION_SCAFFOLD | Live disables export without phase mutation alert | headed | DONE |
| `dashboard/mocks/overview-fixtures.ts` | Preview Admin | Fixture identity | Fixture only | TEST/FIXTURE_ONLY | Keep | smoke | KEEP |
| `dashboard/mocks/agent-fixtures.ts` | synthetic preview case | Fixture notes | Fixture only | TEST/FIXTURE_ONLY | Keep | n/a | KEEP |
| `dashboard/features/cms/validation/link-validation.ts` | synthetic preview number | CMS link validator hint | Dev tooling | TEST/FIXTURE_ONLY | Keep | n/a | KEEP |
| `dashboard/tests/read-only-shell.smoke.spec.ts` | Preview Admin assertion | Preview-mode smoke | Test only | TEST/FIXTURE_ONLY | Keep under preview env | smoke | KEEP |
| `dashboard/tests/live-operational-mode.foundation.spec.ts` | live mode matrix | Regression gate | Test only | LIVE_OPERATIONAL | New Batch 1.5 suite | foundation | DONE |
| `dashboard/lib/read-only/**` helpers | envelope/error/pagination | GET transport | Indirect | LIVE_OPERATIONAL (transport) | Keep; not write-capability | foundation | KEEP |
| `dashboard/.env.example` | MODE/MOCK/MUTATIONS | Build contract docs | No | LIVE_OPERATIONAL | live / false / true | review | DONE |
| Audit / System Health / Reports / PNR views | intentional RO UI | May stay informational | Module-specific | VALID_READ_ONLY_BY_DESIGN | Use explicit RO notice when module capability says so; not global Laravel=RO | Batch 2+ if needed | OPEN |
| `NEXT_PUBLIC_USE_MOCK_DATA` / `ALLOW_MUTATIONS` | env gates | Build/runtime | Indirect | LIVE_OPERATIONAL | Live ops: mock=false; mutations not phase-blocked | foundation | DONE |
| `useDashboardLiveMode` | live flag hook | UI gating | Indirect | LIVE_OPERATIONAL | Keep | many | KEEP |

## Production forbidden markers (post-fix target)

```text
PRODUCTION_LEGACY_READONLY_BANNERS=0
PRODUCTION_PREVIEW_USER_FALLBACKS=0
PRODUCTION_FIXTURE_NOTICES=0
```

No UNKNOWN classifications remain in this table.
