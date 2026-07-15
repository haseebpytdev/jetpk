# AGENTS.md

## Main Instruction
Work precisely. Use the smallest safe change. Do not rewrite unrelated code.

## Before Editing
- **Read and obey `docs/PRODUCTION_DEPLOYMENT_SAFETY.md`** before modifying files
  (no blind namespace, deployment workflow, defensive coding, verification commands).
- Obey `.cursor/rules/laravel-production-safety.mdc` and
  `.cursor/rules/sftp-live-server-rules.mdc` for always-on Cursor enforcement.
- Read `SPEC.md` and this file (`AGENTS.md`).
- **V2 / UI component work:** read `.cursor/skills/ui-design-brain/SKILL.md` and
  `components.md` for component patterns; then **`docs/skills/ui-design-brain-OTA-CONTEXT.md`**
  (or `.cursor/skills/ui-design-brain/OTA-CONTEXT.md` if installed) and
  `.cursor/rules/v2-ui-implementation.mdc` — OTA rules override the skill.
- Read or skim `summary.md` whenever the task touches **booking, suppliers
  (Sabre/Duffel), flight search, holds, payments, or other sections it lists**.
- Identify the minimum set of files needed.
- Do not scan the full project unless necessary.
- Give a short plan before making changes.

## Summary documentation (mandatory)
Keep agent-facing summaries **current in the same change** as the code (do not
leave stale maps behind).

- **`summary.md`:** If you add/remove/rename **public** methods, move logic
  between classes, or change behavior of files **already indexed** there, update
  the relevant table or section and append a **Changelog** row for notable
  module-level edits. If you introduce a new high-traffic service/controller
  cluster, add a short row or subsection so the next agent finds it quickly.
- **`SPEC.md` / `AGENTS.md`:** Edit only when project-wide rules, stack, or
  workflow actually change (same change as the rule update).
- **PHP class docblocks:** Non-trivial classes (large services, adapters, clients)
  keep a **concise docblock above the class**; refresh it when responsibilities or
  main entry points change.

## Coding Rules
- Preserve existing architecture (Laravel 13, services under `app/Services/`,
  adapters under `app/Services/Suppliers/`, DTOs under `app/Data/`, enums under
  `app/Enums/`).
- Preserve existing UI style (Blade + Tailwind + Alpine.js) unless the task is
  a UI redesign.
- Do not introduce new packages unless necessary; if needed, justify and use the
  package manager (`composer require`, `npm install`) with the latest stable
  version.
- Do not hardcode credentials. Use `.env`, `config/*.php`, or the existing
  `SupplierConnection` / credential storage pattern.
- Do not create duplicate helpers, components, routes, services, or controllers
  if an existing one can be reused. Search first.
- Prefer editing existing files over creating new files.
- Do not delete code unless it is confirmed unused or broken.
- Do not re-introduce removed Mock supplier classes.

## Debugging Rules
When fixing a bug:
1. Reproduce or locate the failing path.
2. Identify root cause.
3. Make the smallest fix.
4. Run the relevant test / build / `php artisan` check.
5. Explain changed files only.

## Public CSS cache bust
When you edit `public/css/ota-public.css`, always increment `?v=` on the
stylesheet link in `resources/views/layouts/frontend.blade.php` in the **same
change** (e.g. `?v=83` → `?v=84`).

## Mobile app cache bust
When you edit `public/css/ota-mobile-app.css` or `public/js/ota-mobile-app.js`,
always increment `?v=` on **both** asset links in
`resources/views/layouts/mobile-app.blade.php` in the **same change**
(e.g. `?v=8` → `?v=9`). CSS and JS share one integer version. See
`.cursor/rules/mobile-app-cache-bust.mdc`.

## Sprint workflow
Multi-sprint work follows `.cursor/rules/sprint-workflow.mdc`:

- **Per sprint:** automated tests/audit → fix in-scope only → upload changed files only → defer full manual QA.
- **Pre-deploy audit gate:** every implementation pass must end with the four audits
  in `.cursor/rules/pre-deploy-audit-gate.mdc` (also
  `docs/audits/OTA_PRE_DEPLOY_NO_500_RULE.md`). No SFTP upload when
  `fail>0`, `server_errors>0`, mojibake grep hits, or new `production.ERROR`.
- **Pre-deploy no-500 gate:** before upload, run the checklist in
  [`docs/audits/OTA_PRE_DEPLOY_NO_500_RULE.md`](docs/audits/OTA_PRE_DEPLOY_NO_500_RULE.md)
  (`ota:route-page-health-audit --all` must have `fail=0`).
- **After all sprints:** manual QA (desktop/tablet/mobile; public, booking, agent, admin) → final bug-fix pass.
- **Finance-Reports sprints:** mandatory realistic demo data + calculation/RBAC tests per `.cursor/rules/finance-reports-qa.mdc` and `App\Support\Finance\OtaFinanceDemoScenario`.
- **Mobile public results sprints:** parallel mobile shell beside desktop — inspect `FlightController` + desktop results Blade; **do not edit** `resources/views/frontend/flights/results.blade.php` unless the user explicitly asks. Follow `.cursor/rules/mobile-public-results.mdc` (same pattern as mobile home M1/M2).

## Output Rules
- Be concise.
- Do not paste full files unless requested.
- Show only changed sections or summary.
- **Always** end with the mandatory report in
  `.cursor/rules/phase-completion-report.mdc`: full **Files changed** list,
  **Files to upload (SFTP)**, server SSH clears, verification, and rollback.
- Always mention tests / build commands run (or why they were not run).
- Use the response sections defined in `SPEC.md` ("Response Format").
