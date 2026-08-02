# JP-OPS-03 Saved Traveler Contract

## JSON endpoints (Blade preserved)

| Method | Path | Action |
|--------|------|--------|
| GET | `/customer/travelers?format=json` | index |
| GET | `/customer/travelers/create?format=json` | create form |
| POST | `/customer/travelers?format=json` | store |
| GET | `/customer/travelers/{id}/edit?format=json` | edit form |
| PATCH | `/customer/travelers/{id}?format=json` | update |
| DELETE | `/customer/travelers/{id}?format=json` | destroy |

Validation via `UpsertSavedTravelerRequest`. Ownership via `SavedTravelerPolicy`.

Editing saved travelers does not mutate historical `booking_passengers` records.

Next page: `/customer/travelers`
