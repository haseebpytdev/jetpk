# Trending route CTA contract

## Authorities

| Concern | Component |
|---------|-----------|
| Cheapest fare | `JetpkHomepageRouteFareRefreshService::refreshRouteItem()` → `FlightSearchService::search()` |
| Cheapest date | `travel_date` in `_fare_cache.routes[{id}]` |
| CTA URL builder | `JetpkHomepageRouteSearchUrlBuilder` |
| Public display | `JetpkHomepageSectionData::routesForDisplay()` → `search_url` |
| Admin refresh | `POST /admin/page-settings/home/refresh-fares` |

## TRENDING_CTA_ROOT_CAUSE

Stale or empty manual `cta_url` fields in CMS draft content prevented auto URLs from appearing in the editor. Public rendering already generated `search_url` when `cta_url` was empty, but admins saw blank fields.

## ROUTE_CTA_MODE

Default **AUTO**. Fare refresh persists:

- `cta_mode = auto`
- `cta_url` = generated flight-search URL with origin, destination, cheapest `travel_date`, trip type

Manual override available in CMS with explicit `cta_mode = manual`.

## Consistency rule

After refresh:

`CARD_ORIGIN == SEARCH_URL_ORIGIN`  
`CARD_DESTINATION == SEARCH_URL_DESTINATION`  
`CHEAPEST_DATE == SEARCH_URL_DATE`  
`TRIP_TYPE == SEARCH_URL_TRIP_TYPE`
