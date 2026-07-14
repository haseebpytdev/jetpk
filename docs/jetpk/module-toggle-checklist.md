# JetPK module toggle checklist

JetPK product areas map to **two** toggle layers. Both must align before production.

## Layer 1 — Client profile modules (`clients/jetpk/modules.json`)

Runtime via `OTA_MODULE_*` in `.env`. Seeded by `ota:seed-jetpakistan-client-profile`.

| Key | JetPK v1 default | Product area |
|-----|------------------|--------------|
| `sabre` | `false` | Sabre GDS supplier |
| `al_haider_group_ticketing` | `false` → enable for groups | Group ticketing |
| `accounting` | `false` | Ledger/accounting |
| `hotels` | `false` | Hotels module |
| `visa` | `false` | Visa services |
| `payment_gateway` | `true` | Online payments |
| `dev_cp` | `false` | Developer control panel |
| `staff_panel` | `true` | Staff console |
| `admin_panel` | `true` | Admin console |

## Layer 2 — Platform modules (Dev CP)

Controlled via **Developer CP → Platform Module Control** (`PlatformModuleRegistry`, `PlatformModuleGate`).

| Product area | Registry section | Example module keys |
|--------------|------------------|-------------------|
| **Flights** | `public_website`, `customer_b2c`, `supplier_sabre` | `public_flight_search`, `customer_checkout`, `supplier_search`, `duffel`, `sabre_gds` |
| **Groups** | `public_website`, `agent_b2b` | `group_ticketing` + client `al_haider_group_ticketing` |
| **Umrah** | `public_website` | Umrah/group CMS routes (nav hidden on JetPK) |
| **Hotels** | — | Client `hotels` + future platform key |
| **Tours** | CMS/static | Policy pages under `/pages/{slug}` |
| **Agent portal** | `agent_b2b` | `agent_portal`, `agent_wallet`, `agent_registration` |
| **Customer portal** | `customer_b2c` | `customer_account`, `customer_bookings` |
| **Admin/staff** | `staff_admin` | Client `admin_panel`, `staff_panel` |
| **Suppliers** | `supplier_sabre`, `ticketing` | `supplier_booking`, `sabre_gds`, `sabre_ndc`, `duffel` |
| **Payments** | `finance` | `payment_gateway`, payment proof modules |
| **Reports** | `reports` | Finance/report modules |

## Dev CP readiness (future plans)

When Dev CP **client plans** ship:

1. Plan preset selects platform module states
2. Client profile `modules.json` synced to plan entitlements
3. JetPK nav/footer hides disabled product CTAs (already: Umrah hidden)

## Pre-deploy verification

- [ ] `modules.json` matches server `OTA_MODULE_*`
- [ ] Dev CP platform modules match intended product (on master QA)
- [ ] Disabled modules return 404 or gate message — not 500
- [ ] JetPK nav links only show enabled products
- [ ] Supplier credentials absent for disabled suppliers

## Commands

```bash
php artisan ota:seed-jetpakistan-client-profile
# Dev CP UI: /dev/cp/platform-modules (master only, when enabled)
```

## Master vs JetPK production

| Setting | Master workspace | JetPK production |
|---------|------------------|------------------|
| `OTA_CLIENT_SLUG` | `haseeb-master` | `jetpk` |
| Preview routes | `/jetpk/*` enabled | Optional off |
| Dev CP | May be enabled | Disabled |
