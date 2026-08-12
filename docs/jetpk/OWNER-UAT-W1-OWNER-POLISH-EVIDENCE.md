# OWNER UAT W1 — Owner polish evidence (header + welcome panels)

## Scope

Final Wave-1 owner polish on `phase/jetpk-owner-uat-wave-1-portals-public-shell`:

1. Signed-out public header: single **Login** CTA (no Sign up / Log in / Sign up / Book Now)
2. Signed-in Customer/Agent header: no Login, no Book Now; profile dropdown is account entry
3. Compact Agent + Customer welcome/insight panels on overview dashboards
4. Preserve prior Wave-1 auth / portal layout / RBAC / footer / F005 / OLS

## Commits

- `6b3f2b9` — Login-only header + portal welcome panels
- `b178503` — remove duplicate Login from mobile drawer

## Media slot

Welcome panel uses CSS/SVG route motif only (`data-media-slot="portal-welcome-illustration"`).
No stock photography. Future approved JetPakistan asset can replace the decorative SVG without layout changes.

## Files

- `frontend/components/layout/SiteHeader.tsx`
- `frontend/components/navigation/AccountMenu.tsx`
- `frontend/components/navigation/MobileNavigation.tsx`
- `frontend/features/portal/shell/PortalWelcomePanel.tsx` (new)
- `frontend/features/agent-dashboard/overview/AgentOverviewPage.tsx`
- `frontend/features/customer-dashboard/overview/DashboardOverviewPage.tsx`
- `frontend/tests/jp-ui-02-header-footer.spec.ts`

## Production verification

Script: `tmp/jp-w1-polish-accept.cjs` → `tmp/jp-w1-polish-accept.json`

Result: `ok: true`, `fails: []`

| Gate | Status |
|---|---|
| OWNER_W1_SIGNED_OUT_LOGIN_CTA | PASS |
| OWNER_W1_GLOBAL_SIGNUP_REMOVED | PASS |
| OWNER_W1_AUTHENTICATED_BOOK_NOW_REMOVED | PASS |
| OWNER_W1_HEADER_RESPONSIVE | PASS |
| OWNER_W1_AGENT_WELCOME_PANEL | PASS |
| OWNER_W1_CUSTOMER_WELCOME_PANEL | PASS |
| OWNER_W1_AUTH_REGRESSION | PASS |

Widths checked: 390, 768, 935, 1024, 1280, 1366, 1440, 1600, 1920 (+ zoom samples).

OLS hash unchanged: `612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c`

## Result

`OWNER_UAT_WAVE_1=PASS_READY_FOR_FINAL_OWNER_ACCEPTANCE`
