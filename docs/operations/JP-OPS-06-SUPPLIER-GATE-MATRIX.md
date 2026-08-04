# JP-OPS-06 Supplier Gate Matrix

No `.env` changes in this phase. Existing gates preserved:

| Domain | Gates |
|--------|--------|
| Sabre cancel | `SABRE_CANCEL_*`, `admin_cancel_live_call_enabled` |
| Sabre ticketing | `ticketing_enabled`, `ticketing_live_call_enabled`, GDS confirm phrase |
| Duffel ticketing | Adapter resolution; tests mock `DuffelSupplierTicketingAdapter` |

Blocked paths log safe reasons; no fabricated supplier success.
