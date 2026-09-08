# Featured authority root cause — Closure-05

## HOME-FEATURED-001
CMS stored airline/from/to/price as if they were commercial authority. Runtime only honored `inventory_id` when present; otherwise `GroupTicketFeaturedDealSource` independently selected eligible inventory from `GroupInventorySearchService`, causing CMS/live mismatch.

## HOME-FEATURED-002
Featured `href` used `route('group-ticketing.show')` → legacy Blade `/groups/package/{id}`.

## HOME-FEATURED-003
No TARGET→inventory resolver. Editorial price could appear in CMS while live card used unrelated inventory.

## HOME-FEATURED-004
No availability gate on homepage advertising; cards could link to unavailable detail pages.

## Fix
- CMS = TARGET (origin, destination, optional airline) + editorial only
- `FeaturedDealInventoryResolver` cascade: exact airline cheapest → same sector → global deterministic fallback; dedupe across slots
- Commercial fields + `href` from one `GroupInventory` via `GroupTicketFeaturedDealSource`
- Legacy CMS price ignored on normalize/publish
