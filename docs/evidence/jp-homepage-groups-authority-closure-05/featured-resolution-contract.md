# Featured resolution contract — Closure-05

## CMS authority (per slot)
- `from` / `to` (required IATA targets)
- `airline` (optional preferred airline)
- `title`, `badge`, `description`, `image`, `image_alt`, `enabled`

## Commercial authority (resolved)
- `inventory_id`, `public_id`
- `airline`, `from`, `to`, `depart`, `price`, `price_label`
- `availability`, `href` (`/groups/{publicId}`)
- `resolution_rule`: `exact_airline` | `same_sector` | `global_fallback`

## Cascade
1. Exact origin + destination + preferred airline → cheapest eligible inventory
2. Else same sector → cheapest eligible
3. Else global eligible pool → price ASC, departure_date ASC, id ASC
4. Suppress slot if no eligible inventory
5. Do not reuse inventory across slots when alternatives exist

## Invariant
`ONE_RESOLVED_GROUP_INVENTORY` drives airline, sector, departure, price, availability, and href.
