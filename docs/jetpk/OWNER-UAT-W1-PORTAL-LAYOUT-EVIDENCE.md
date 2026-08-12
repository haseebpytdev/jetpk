# OWNER-UAT-W1 — Portal Layout Evidence

## Root cause

`.jp-portal` in `frontend/styles/kit-public.css` forced:

```css
display: grid;
grid-template-columns: 250px 1fr;
```

Next `PortalShell` renders a single visible flex frame as a grid child. With the topbar `display:none` on desktop, that frame occupied the **first 250px column**. Computed production evidence at 1440:

- portal `gridTemplateColumns: 250px 1190px`
- frame width: **250px**
- main (`flex:1`): **0px**

## Repair

- `.jp-portal` → `display:block; width:100%`
- Portal frame → `jp-app-portal__frame` flex row, `max-width: 90rem`, main `flex:1; min-width:0`
- Collapsible RBAC nav groups
- Agent/Customer overview denser grids
- Customer post-login → `/customer/dashboard`
- Compact portal footer (marketing footer hidden on Agent/Customer layouts)

## Browser gates (post-deploy)

Measured in real Chromium after rebuild (`BUILD_ID=hdqaMNQ8mBHq9ZMOjzidG`).

| Surface | Viewport | portal display | main width | Result |
|---|---|---|---|---|
| Agent dashboard | 1024 | block | 753 | PASS (fills frame beside sidebar) |
| Agent dashboard | 1366 | block | 1058 | PASS |
| Agent dashboard | 1440 | block | 1132 | PASS |
| Agent notifications | 1440 | block | 1132 | PASS |
| Customer login landing | 1440 | block | `/customer/dashboard`, main 1132 | PASS |
| Customer bookings filters | 1440 | row/wrap | bar width 1132 | PASS |

Pre-fix at 1440: portal grid `250px 1190px`, frame **250px**, main **0px**.

OLS: `612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c` MATCH

Customer landing also required stripping accidental `/jetpk/...` parity prefixes from login redirects so OLS serves the Next overview.
