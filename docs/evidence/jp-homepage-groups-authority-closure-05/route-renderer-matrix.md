# Group route / renderer matrix — Closure-05

| URL | Route name | Renderer | Type | Current UI | Public | Action |
|-----|------------|----------|------|------------|--------|--------|
| `/groups` | `group-ticketing.hub` | Laravel closure → Next :3010 | NEXT proxy | YES | YES | Keep hub proxy |
| `/groups/search` | `group-ticketing.search` | `GroupTicketingSearchController@index` | NEXT proxy | YES | YES | HTML proxied to Next search |
| `/groups/search/data` | `group-ticketing.search.data` | `GroupTicketingSearchController@searchData` | JSON | n/a | YES | Laravel authority retained |
| `/groups/search/facets` | `group-ticketing.search.facets` | `GroupTicketingSearchController@searchFacets` | JSON | n/a | YES | Laravel authority retained |
| `/groups/search/results` | `group-ticketing.search.results` | `GroupTicketingSearchController@results` | JSON | n/a | YES | Laravel authority retained |
| `/groups/facets` | `group-ticketing.facets` | `GroupTicketingSearchController@facets` | JSON | n/a | YES | Laravel authority retained |
| `/groups/package/{inventory}` | `group-ticketing.show` | `GroupTicketingSearchController@show` | REDIRECT | YES | YES | 302 → `/groups/{publicId}` |
| `/groups/{packageId}` | `group-ticketing.next-detail` | Laravel closure → Next :3010 | NEXT proxy | YES | YES | Deep-link detail UI |
| `/umrah-groups` | `umrah-groups.index` | redirect | REDIRECT | YES | YES | → `/groups/search` |
| `/umrah-groups/{package}` | `umrah-groups.show` | redirect | REDIRECT | YES | YES | → `/groups/{publicId}` |
| Featured deal href | n/a | `GroupTicketFeaturedDealSource` | Next path | YES | YES | `/groups/{publicId}` |
| `/groups/{id}/passengers` | `group-ticketing.booking.passengers` | `GroupTicketingBookingController` | NEXT proxy | YES | auth | Checkout passengers |
| `/groups/booking/{ref}/review` | `group-ticketing.booking.review` | booking controller | NEXT proxy | YES | auth | Review step |
| `/groups/booking/{ref}/payment` | `group-ticketing.booking.payment` | booking controller | NEXT proxy | YES | auth | Payment step |
| `/groups/booking/{ref}/confirmation` | `group-ticketing.booking.confirmation` | booking controller | NEXT proxy | YES | auth | Confirmation |

Legacy Blade views remain in repo for admin/regression but are no longer returned for public HTML entry points covered above.
