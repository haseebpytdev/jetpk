# OWNER UAT W1 — Owner polish evidence (header + welcome panels)

## Scope

Final Wave-1 owner polish on `phase/jetpk-owner-uat-wave-1-portals-public-shell`:

1. Signed-out public header: single **Login** CTA (no Sign up / Log in / Sign up / Book Now)
2. Signed-in Customer/Agent header: no Login, no Book Now; profile dropdown is account entry
3. Compact Agent + Customer welcome/insight panels on overview dashboards
4. Preserve prior Wave-1 auth / portal layout / RBAC / footer / F005 / OLS

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

## Verification

Production script: `tmp/jp-w1-polish-accept.cjs` → `tmp/jp-w1-polish-accept.json`

Gates tracked in `OWNER-UAT-W1-FINAL-REPORT.md`.
