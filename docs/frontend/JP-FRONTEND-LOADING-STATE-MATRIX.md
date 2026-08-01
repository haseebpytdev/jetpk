# JP-FRONTEND Loading State Matrix

| Route / region | Pattern | aria-busy |
|---|---|---|
| `/flights/results` | Route skeleton | Yes |
| `/flights/fare-selection` | Route skeleton | Yes |
| `/flights/return-options` | Route skeleton | Yes |
| `/booking/passengers` | Route skeleton | Yes |
| `/booking/review` | Route skeleton | Yes |
| `/booking/payment/status` | Route skeleton + poll status | Yes |
| `/lookup-booking` | Route skeleton | Yes |
| `/groups/search` | Card list skeleton | Yes |
| `/customer/bookings` | Table skeleton | Yes |
| `/agent/bookings` | Table skeleton | Yes |
| Airport autocomplete | Inline list status | Yes |
| Auth forms | Button pending label | On submit |
| Payment poll | Live region + manual refresh | Yes |
| Background filter refresh | Retain prior results | No page blank |

Components: `LoadingRegion`, `RouteLoadingSkeleton`, `TableLoadingSkeleton`, `CardListLoadingSkeleton`, `Skeleton`.
