# JP-NEXT-PERF-02B — engineering fixes

## Local engineering commits (branch `phase/jp-flight-perf-01`)

- View-cache / paint budget / validate timing (earlier 02B chain)
- `9e1728d6` pool-release hard handoff
- `e28e9e7c` stronger pool release + no gates I/O on Book Now
- `d0f28ef4` disable Link/font prefetch starvation; lazy logos

**PERF_02B_LOCAL_ENGINEERING_SHA=`d0f28ef40baa1f4469451ff593197bfb9ca69a2c`**

## Isolated deploy trees (from production runtime `98f92ea9…`, no MOFA)

Final deploy: **`568efa8d2d9e916370b6dc49a36bcbbc26ff268a`** (r8)

Public build: **`U9-V-YGZgQ3qKayMCp4BX`**

## Runtime files in final isolated tree

- `frontend/app/layout.tsx`
- `frontend/components/layout/SiteHeader.tsx`
- `frontend/components/navigation/PublicFloatingActionDock.tsx`
- `frontend/components/ui/AirlineLogoMark.tsx`
- `frontend/components/ui/LinkButton.tsx`
- `frontend/features/flight-details/hooks/use-revalidation.ts`
- `frontend/features/flight-results/components/FlightResultsPage.tsx`
- `frontend/features/flight-results/hooks/use-flight-results.ts`
- `frontend/features/flight-results/services/flight-results-api.ts`
- `frontend/features/standard-booking/services/commerce-gates-service.ts`
- `frontend/lib/api/laravel-action-client.ts`

## Preserved (not modified for regression)

Review/Payment loading architecture, Groups SSR inventory, Traveler READY contract, Return Departure/Arrival UI, logos branding rules, PageHero, Home spacing intent, Ask JetPakistan, MOFA undeployed.
