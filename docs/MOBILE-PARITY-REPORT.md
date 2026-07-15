# MOBILE-PARITY-REPORT (final)

**Baseline** `6fbfae4` · read-only analysis · **not execution-verified.**

## 1. Mechanism (unchanged by this programme)

```php
shouldUseMobileShell($request, $pageKey)
  = config('ota-mobile.mobile_pages')[$pageKey] ?? false   // per-page OPT-IN
    && prefersMobileExperience($request);                   // cookie ota_view_mode, else UA regex
```
Map: **63 keys · 51 `true` · 1 explicit `false` (`profile.edit`) · 0 `staff.*`/`admin.*`.**
The programme **did not modify** the map, the cookie, or the UA regex.

## 2. Parity

| Role | Pages | Mobile view | Themed by this package | Verdict |
|---|---|---|---|---|
| **Agent** | 25 | 25 | ✅ all | **Full parity** |
| **Customer** | 9 | 6 | ✅ all 6 | Gap: `travelers.*` (see §3) |
| **Public / booking** | 15 | 15 | ✅ all | **Full parity** — the revenue path |
| **Auth entry** | 5 | 5 | ✅ all | **Full parity** |
| Internal Staff | 13 | 0 | n/a | **By design** — desktop ops console |
| Platform Admin | 78 | 0 | n/a | **By design** |

**All 51 controller-rendered mobile views are themed.** 0 orphan mobile views.

## 3. The one open gap — customer travelers

`agent.travelers.{index,create,edit}` are mapped; **`customer.travelers.*` is absent from
`mobile_pages`**. So a customer managing travelers on a phone gets the responsive desktop portal page
rather than the app shell.

**Not closed here, deliberately.** Closing it needs a config key + views + a **controller branch**
(backend). The shared travelers view already ships desktop-table + mobile-cards, so customers get a
usable page. **Recommendation: accept the fallback** — it is a low-frequency page; revisit if usage
says otherwise.

## 4. Staff/Admin remain desktop-only

Correct and intentional — it matches the stated architecture (ops = external-looking desktop
dashboard). Their small-viewport usability is handled by the dashboard programme's Phase 7 ops-grid
fix, not here.
