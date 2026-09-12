# JetPakistan Media Owner Action List

Generated: 2026-09-12T21:07:31.256Z

## Counts
- OWNER_ASSETS_REQUIRED_RAW=84
- OWNER_ASSETS_REQUIRED_DEDUPED=41
- OWNER_ASSETS_REQUIRED_P0=0
- OWNER_ASSETS_REQUIRED_P1=41
- OWNER_ASSETS_OPTIONAL=0
- OWNER_ASSETS_ALREADY_SATISFIED=0

## Other

| ID | Page section | Purpose | Current | Req/Opt | Priority | Aspect | Min size |
|----|--------------|---------|---------|---------|----------|--------|----------|
| JP-MEDIA-001 | detail-documents-card | contextual photography or illustration | `{{ $downloadUrlFor($document) }}` | required | P1 | 16:9 | 1200×675 |
| JP-MEDIA-002 | detail-help-card | contextual photography or illustration | `{{ route(` | required | P1 | 16:9 | 1200×675 |
| JP-MEDIA-003 | detail-payment-card | contextual photography or illustration | `{{ $loginUrl }}` | required | P1 | 16:9 | 1200×675 |
| JP-MEDIA-005 | dest-card | contextual photography or illustration | `{{ $href }}` | required | P1 | 16:9 | 1200×675 |
| JP-MEDIA-007 | route-card | contextual photography or illustration | `{{ $href }}` | required | P1 | 16:9 | 1200×675 |
| JP-MEDIA-013 | summary-card | contextual photography or illustration | `{{ $summary[` | required | P1 | 16:9 | 1200×675 |
| JP-MEDIA-022 | tournest-home-main | contextual photography or illustration | `#hotels` | required | P1 | 16:9 | 1200×675 |
| JP-MEDIA-025 | context-card | contextual photography or illustration | `{{ route($routeName, [` | required | P1 | 16:9 | 1200×675 |
| JP-MEDIA-027 | default-traveler-card | contextual photography or illustration | `{{ route($routePrefix.` | required | P1 | 16:9 | 1200×675 |
| JP-MEDIA-028 | about | destination | `{{ $renderer->resolveDestination((string` | required | P1 | 16:9 | 1200×675 |
| JP-MEDIA-031 | card-payment | contextual photography or illustration | `{{ rtrim(client_theme()->frontendThemeUr` | required | P1 | 16:9 | 1200×675 |
| JP-MEDIA-032 | lookup | destination | `{{ $renderer->resolveDestination((string` | required | P1 | 16:9 | 1200×675 |
| JP-MEDIA-034 | faq | destination | `{{ $renderer->resolveDestination((string` | required | P1 | 16:9 | 1200×675 |
| JP-MEDIA-036 | drawer | destination | `{{ app(\App\Services\Client\ClientPageRe` | required | P1 | 16:9 | 1200×675 |
| JP-MEDIA-037 | header | destination | `{{ app(\App\Services\Client\ClientPageRe` | required | P1 | 16:9 | 1200×675 |
| JP-MEDIA-039 | hero | homepage hero photography | `{{ $preload[` | required | P1 | 16:9 | 1200×675 |
| JP-MEDIA-040 | index | contextual photography or illustration | `{{ client_route($card[` | required | P1 | 16:9 | 1200×675 |
| JP-MEDIA-041 | featured-fares | homepage hero photography | `#ota-home-hero` | required | P1 | 16:9 | 1200×675 |

## Admin/CMS

| ID | Page section | Purpose | Current | Req/Opt | Priority | Aspect | Min size |
|----|--------------|---------|---------|---------|----------|--------|----------|
| JP-MEDIA-004 | action-card | contextual photography or illustration | `{{ $href }}` | required | P1 | 16:9 | 1200×675 |
| JP-MEDIA-010 | index | contextual photography or illustration | `route($card[` | required | P1 | 16:9 | 1200×675 |
| JP-MEDIA-024 | dashboard-sidebar-admin | group travel | `#sidebar-group-ticketing-submenu` | required | P1 | 16:9 | 1200×675 |

## Groups

| ID | Page section | Purpose | Current | Req/Opt | Priority | Aspect | Min size |
|----|--------------|---------|---------|---------|----------|--------|----------|
| JP-MEDIA-006 | group-card | group travel | `{{ $href }}` | required | P1 | 16:9 | 1200×675 |
| JP-MEDIA-008 | index | group travel | `{{ route(` | required | P1 | 16:9 | 1200×675 |
| JP-MEDIA-009 | form | group travel | `{{ route(` | required | P1 | 16:9 | 1200×675 |
| JP-MEDIA-014 | confirmation | group travel | `{{ $checkoutSummary[` | required | P1 | 16:9 | 1200×675 |
| JP-MEDIA-015 | result-card | group travel | `{{ e($card[` | required | P1 | 16:9 | 1200×675 |
| JP-MEDIA-016 | result-row | group travel | `{{ e($card[` | required | P1 | 16:9 | 1200×675 |
| JP-MEDIA-017 | review | group travel | `{{ $checkoutSummary[` | required | P1 | 16:9 | 1200×675 |
| JP-MEDIA-018 | search | group travel | `{{ client_route(` | required | P1 | 16:9 | 1200×675 |
| JP-MEDIA-019 | show | group travel | `{{ client_route(` | required | P1 | 16:9 | 1200×675 |
| JP-MEDIA-021 | ota-hero-group-search | homepage hero photography | `{{ client_route(` | required | P1 | 16:9 | 1200×675 |
| JP-MEDIA-023 | package-card | group travel | `{{ client_route(` | required | P1 | 16:9 | 1200×675 |

## Customer

| ID | Page section | Purpose | Current | Req/Opt | Priority | Aspect | Min size |
|----|--------------|---------|---------|---------|----------|--------|----------|
| JP-MEDIA-011 | default-traveler-card | contextual photography or illustration | `{{ route($routePrefix.` | required | P1 | 16:9 | 1200×675 |

## Support

| ID | Page section | Purpose | Current | Req/Opt | Priority | Aspect | Min size |
|----|--------------|---------|---------|---------|----------|--------|----------|
| JP-MEDIA-012 | support-card | contextual photography or illustration | `mailto:{{ $supportEmail }}` | required | P1 | 16:9 | 1200×675 |
| JP-MEDIA-033 | jp-support-card | contextual photography or illustration | `{{ $waUrl }}` | required | P1 | 16:9 | 1200×675 |
| JP-MEDIA-035 | support | destination | `{{ $renderer->resolveDestination((string` | required | P1 | 16:9 | 1200×675 |

## Flights

| ID | Page section | Purpose | Current | Req/Opt | Priority | Aspect | Min size |
|----|--------------|---------|---------|---------|----------|--------|----------|
| JP-MEDIA-020 | ota-hero-flight-search | homepage hero photography | `#ota-flight-search` | required | P1 | 16:9 | 1200×675 |

## Destination/content pages

| ID | Page section | Purpose | Current | Req/Opt | Priority | Aspect | Min size |
|----|--------------|---------|---------|---------|----------|--------|----------|
| JP-MEDIA-026 | home-destinations-manager | destination | `{{ $existingAsset->public_url }}` | required | P1 | 16:9 | 1200×675 |
| JP-MEDIA-038 | destinations | destination | `{{ $ctaUrl }}` | required | P1 | 16:9 | 1200×675 |

## Agent

| ID | Page section | Purpose | Current | Req/Opt | Priority | Aspect | Min size |
|----|--------------|---------|---------|---------|----------|--------|----------|
| JP-MEDIA-029 | landing | homepage hero photography | `{{ $renderer->resolveDestination((string` | required | P1 | 16:9 | 1200×675 |
| JP-MEDIA-030 | landing | destination | `{{ $renderer->resolveDestination((string` | required | P1 | 16:9 | 1200×675 |
