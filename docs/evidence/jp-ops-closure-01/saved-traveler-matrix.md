# Saved traveler matrix (JP-OPS-CLOSURE-01)

```
SAVE_TRAVELER=Customer/Agent portal CRUD (existing)
EDIT_TRAVELER=existing
DELETE_TRAVELER=existing
DEFAULT_TRAVELER=is_default sync (existing)
PASSENGER_FORM_AUTOFILL=NEW checkout endpoints + Next picker
```

## Checkout endpoints (new)

- `GET /booking/saved-travelers` — masked list, customer only
- `GET /booking/saved-travelers/{traveler}` — owner fill with full document

```
SAVED_TRAVELER_IDOR=PASS (policy + CheckoutSavedTravelerTest)
DOCUMENT_LIST_MASKING=PASS
SENSITIVE_FIELD_STORAGE=PASS (encrypted cast)
DEFAULT_TRAVELER_DETECTED=YES (default_traveler_id)
AUTOFILL_OCCURRED=Next lead adult when empty
MANUAL_EDIT_ALLOWED=YES
SAME_TRAVELER_SELECTED_TWICE_HANDLED=YES (client warning)
EXPIRED_DOCUMENT_BLOCK_OR_WARNING=WARNING
```

Layer A tests: `CheckoutSavedTravelerTest` + existing `SavedTravelerTest`
