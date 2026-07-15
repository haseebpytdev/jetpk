# JetPK dedicated server — data migration checklist

**Phase:** 7I  
**Classification:** Planning only — **no secrets**, **no raw credentials**

---

## A. Client profile

| Field | Required value | Status (7H audit) |
|-------|----------------|-------------------|
| `slug` | `jetpk` | Present |
| `is_active` | `true` | Active |
| `active_frontend_theme` | `jetpakistan` | Set |
| `active_admin_theme` | `jetpakistan` | Set |
| `asset_profile` | `jetpk-assets` | Set |
| `is_master_profile` | `false` | Required on dedicated server |

---

## B. Branding

| Field | Expected |
|-------|----------|
| Display name | JetPakistan |
| Primary color | `#00843D` |
| Logo | `client-assets/jetpk-assets/logo/logo.svg` |
| Favicon | `client-assets/jetpk-assets/favicon/favicon.ico` |
| Support email | `ticketingjp@jetpakistan.com` (or final configured value) |

**7H audit:** Branding row **present**.

---

## C. Page Builder

| Table / key | 7H audit | Action |
|-------------|----------|--------|
| `client_page_settings` (home, about, support, group-search, login, register, footer) | **0 rows** | **MUST SEED** (approved pass) |
| `client_page_assets` | **0 rows** | Upload via Page Settings after seed |
| `client_theme_palettes` | **1 row** | Verify on production |

---

## D. Users

| Item | Notes |
|------|-------|
| Seeded users | **Reuse same users from Master Client** — export with DB seed pass |
| Platform admin | Not a blocker if `bobsif` absent locally — reuse Master test admin users |
| Passwords | Not in package; reset on dedicated server if needed |

---

## E. Suppliers (masked — enter in Admin UI)

| Supplier | 7H status |
|----------|-----------|
| sabre, pia_ndc, airblue, duffel, iati, travelport, amadeus | Present, **disabled**, credentials **not exported** |
| Al-Haider group ticketing | Enable when groups go live |

**Policy:** Credentials in **Admin → Supplier & API Settings** only. Encrypted in DB.

---

## F–J. Group ticketing, payments, mail, storage, scheduler

See [jetpk-dedicated-cutover-plan.md](jetpk-dedicated-cutover-plan.md) sections 9, 14–16.

**Scheduler:** `group-ticketing:sync-inventory`, `group-ticketing:release-expired`, `schedule:run` cron.

---

## Pre-cutover data gate

- [ ] jetpk profile + branding imported
- [ ] Page settings seeded (or deferred with approval)
- [ ] Platform admin exists
- [ ] Supplier credentials entered on server (masked)
- [ ] SMTP tested
- [ ] No other client profiles in dedicated DB
