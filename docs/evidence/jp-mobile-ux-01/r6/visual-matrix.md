# Portal list / table inventory (R6)

## Classification
Authenticated lists/tables audited via live portal screenshots + overflow checks.

| Portal | Surface | Pattern |
|---|---|---|
| Customer | Bookings | RESPONSIVE_CARD / STACKED_ROWS |
| Customer | Travelers | RESPONSIVE_CARD |
| Agent | Bookings | RESPONSIVE_CARD / STACKED_ROWS |
| Agent | Wallet / Ledger | PRIORITY_COLUMNS + STACKED_ROWS (display-only) |
| Staff | Bookings | PRIORITY_COLUMNS / RESPONSIVE_CARD |
| Admin | Bookings / Agents / Markups | PRIORITY_COLUMNS; CONTROLLED_HORIZONTAL_SCROLL only inside table shells where present |

PORTAL_LISTS_TOTAL≈10
PORTAL_TABLES_TOTAL≈6

MOBILE_LIST_DATA_LOSS=0
MOBILE_LIST_ACTION_LOSS=0
TABLE_CLIPPING=0
TABLE_ACTION_LOSS=0
