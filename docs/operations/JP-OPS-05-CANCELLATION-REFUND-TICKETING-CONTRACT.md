# JP-OPS-05 Cancellation Refund Ticketing Contract

## Cancellation review (connected JSON)

| Action | Executes supplier cancel? |
|--------|---------------------------|
| approve | No |
| reject | No |
| process | **Blocked for Next JSON** → `external_execution_required` (JP-OPS-06) |

## Refund review (connected JSON)

| Action | Settles production refund? |
|--------|----------------------------|
| approve | No — review only |
| reject | No |
| mark-paid | **Blocked for Next JSON** → `external_execution_required` (JP-OPS-06) |

## Ticketing

| Action | Status |
|--------|--------|
| issue-ticket | JP-OPS-06 execution dependency |
| Queue visibility | Read via `/api/dashboard/tickets` |

No frontend ticket number or PNR fabrication. Supplier failures shown generically in overview only.
