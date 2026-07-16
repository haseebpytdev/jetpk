# JetPK Portal Parity Audit

**Date:** 2026-07-16  
**Branch:** `jetpk-production`  
**Scope:** Customer, Agent, Agent Staff only (excludes Platform Admin, Internal Staff, Parwaaz Master)

## Active Claude / JetPK portal integration

| Role | Theme area | Layout shim | Canonical shell | Asset version |
|------|------------|-------------|-----------------|---------------|
| Customer | `customer` / `jetpakistan` | `themes/customer/jetpakistan/layouts/customer-account.blade.php` | `themes/frontend/jetpakistan/layouts/portal` (`portalVariant=customer`) | `portal.css` `?v=42` |
| Agent + Agent Staff | `agent` / `jetpakistan` | `themes/agent/jetpakistan/layouts/agent-portal.blade.php` | same portal layout (`portalVariant=agent`) | `portal.css` `?v=42` |
| Mobile portal | `mobile` / `jetpakistan-app` | `themes/mobile/jetpakistan-app/layouts/mobile-app.blade.php` | shared mobile shell + bottom nav | `app.css` `?v=5` |

**No** `themes/agent-staff/jetpakistan/` tree — agency staff share the agent portal area and `profile.edit-agent` view path.

## Navigation sources (post-closure)

| Control | Source file |
|---------|-------------|
| Customer sidebar | `components/portal/nav-customer.blade.php` |
| Agent sidebar | `components/portal/nav-agent.blade.php` |
| Profile + Logout footer (all roles) | `components/portal/nav-account-footer.blade.php` |
| Top-right avatar | `layouts/portal.blade.php` (`jp-portal-top-profile`) |
| Mobile bottom nav | `layouts/partials/mobile-app-bottom-nav.blade.php` |
| Logout route | `POST /logout` (`routes/auth.php`, name `logout`) |

## Root causes found

1. **Profile seam:** `ProfileController` returned legacy `profile.edit-frontend` / `profile.edit-agent` with `ota-public.css` push — Parwaaz-facing styling inside JetPK portal shell.
2. **Missing sidebar account actions:** JetPK `nav-customer` / `nav-agent` lacked Profile and Logout; top header had text Profile only.
3. **Incomplete agent nav:** JetPK agent sidebar omitted commissions, deposits, ledger, reports, statements present in legacy `layouts/agent-portal.blade.php`.
4. **Mobile agent account tab:** Bottom nav “Agency” tab did not route to profile; no mobile agent profile view or logout.
5. **Resolver gap:** No themed `profile/edit` under `themes/{customer,agent}/jetpakistan/` — `client_view('profile.edit')` fell back to legacy profile views.

## Shared controller change (safe)

| File | Change | Safety |
|------|--------|--------|
| `ProfileController.php` | `client_view_exists()` → themed profile when present; mobile agent profile branch; legacy fallback unchanged | Non-JetPK clients without themed profile still render `profile.edit-frontend` / `profile.edit-agent` |

## Preserved (unchanged)

- Booking, supplier, payment, permission middleware
- `ota_view_mode` cookie system
- Parwaaz/default-client legacy layouts (`layouts/customer-account`, `layouts/agent-portal`)
- Admin/staff ops dashboard (`themes/admin/jetpakistan`)

## Verification commands

```bash
php artisan optimize:clear
php artisan test tests/Feature/Jetpk/JetpkPortalParityTest.php
php artisan ota:route-page-health-audit --all
```
