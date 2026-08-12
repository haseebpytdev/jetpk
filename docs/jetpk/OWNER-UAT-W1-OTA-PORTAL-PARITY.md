# OWNER-UAT-W1 — OTA Portal Parity

Branch: `phase/jetpk-owner-uat-wave-1-portals-public-shell`  
Auth prerequisite commit: `f874b5d`  
OLS integrity (auth/wave-1): `OWNER_UAT_AUTH_OLS_INTEGRITY=PASS`  
Hash: `612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c`

## Method

Three-way comparison:

1. Master OTA (`C:\Users\khadi\ota`) — operational IA / workflows (read-only)
2. JetPakistan Laravel domain/API capability
3. JetPakistan Next Agent/Customer portals

## Agent capabilities

| OTA_CAPABILITY | JETPK_BACKEND_AVAILABLE | JETPK_NEXT_PRESENT | ROLE | REQUIRED | ACTION | RESULT |
|---|---|---|---|---|---|---|
| Overview / dashboard | yes | yes (redesigned compact KPIs) | Agent + Agent Staff | yes | rebuild overview density + wallet/attention panels | DONE |
| Flight bookings list | yes | yes | BookingsView | yes | keep | DONE |
| New / create booking | yes | yes | BookingsCreate | yes | keep in Bookings group | DONE |
| Wallet | yes | yes | WalletView + module | yes | Finance group | DONE |
| Deposits | yes | yes | WalletView + deposits module | yes | Finance group | DONE |
| Ledger | yes | yes | LedgerView + module | yes | Finance group | DONE |
| Finance statement | yes | yes | reports/ledger + reports module | conditional | Finance group | DONE |
| Invoices | yes | yes | WalletView | conditional | Finance group | DONE |
| Commissions | yes | yes | Agency owner | owner-only | Finance group | DONE |
| Reports | yes | yes | ReportsView + module | conditional | Finance group | DONE |
| Agency profile | yes | yes | AgencyView | yes | Agency group | DONE |
| Travelers | yes | yes | TravelersManage + module | yes | Agency group | DONE |
| Agency staff | yes | yes | StaffManage + module | owner/authorized | Agency group | DONE |
| Support tickets | yes | yes | SupportManage + module | yes | Support group | DONE |
| Notifications | yes | yes | all agent portal users | yes | Support group | DONE |
| Profile | yes | yes | all | yes | Account group | DONE |
| Security | yes | yes | all | yes | Account group | DONE |
| Payments list (legacy dump) | yes | page exists | WalletView | no (not in OTA console primary nav) | remove from primary nav; route retained | DONE |
| Accounting ledger (duplicate finance) | yes | page exists | LedgerView | no in primary OTA console | remove from primary nav; route retained | DONE |
| Shared Agent / Agent Staff shell | yes | yes (`AgentDashboardShell`) | both | yes | same shell; RBAC via capabilities API | DONE |

## Customer capabilities

| OTA_CAPABILITY | JETPK_BACKEND_AVAILABLE | JETPK_NEXT_PRESENT | ROLE | REQUIRED | ACTION | RESULT |
|---|---|---|---|---|---|---|
| Overview / dashboard | yes | yes (compact rebuild) | Customer | yes | denser KPI + bookings/actions layout | DONE |
| My bookings | yes | yes | Customer | yes | Bookings group | DONE |
| Payments | yes | yes | Customer | conditional | Finance group | DONE |
| Invoices | yes | yes | Customer | conditional | Finance group | DONE |
| Saved travelers | yes | yes | Customer | yes | Travel group | DONE |
| Support | yes | yes | Customer | yes | Support group | DONE |
| Notifications | yes | yes | Customer | yes | Support group | DONE |
| Profile | yes | yes | Customer | yes | Account group | DONE |
| Security | yes | yes | Customer | yes | Account group | DONE |
| No agent/admin modules | n/a | enforced by customer shell | Customer | yes | keep isolated shell | DONE |

## Public signed-in shell

| OTA_CAPABILITY | JETPK_BACKEND_AVAILABLE | JETPK_NEXT_PRESENT | ROLE | REQUIRED | ACTION | RESULT |
|---|---|---|---|---|---|---|
| Profile entry for portal | yes | AccountMenu | signed-in | yes | Overview inside profile dropdown | DONE |
| Standalone Dashboard header link | n/a | removed | signed-in | no | remove desktop + mobile duplicate | DONE |
| Compact theme control | yes | ThemeSwitch | all | yes | smaller footprint | DONE |
| Compact currency control | yes | CurrencySelector compact | all | yes | PKR compact trigger | DONE |
| Book Now no wrap | yes | LinkButton whitespace-nowrap | all | yes | keep primary CTA | DONE |

## Notes

- Agent Staff and Agency Owner share one Next shell; differences are permission/module derived.
- No commercial mutations performed during Wave 1.
- UNKNOWN capability rows: none at closure.
