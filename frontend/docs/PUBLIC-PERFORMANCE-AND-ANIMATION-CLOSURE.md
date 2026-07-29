# Public Performance and Animation Closure (JP-FE-13)

## Performance

- Public CMS routes use `force-dynamic` to avoid build-time Laravel dependency and stale static copies
- Laravel API fetches use 3s timeout (`fetchWithTimeout`)
- Turnstile script loaded only on contact/support/booking lookup pages
- No duplicate public config fetch per request beyond layout + page services (revalidate 60–300s)
- Route-level code splitting preserved (build output shows ~122 kB for CMS pages)

## Animation

- No fake progress or blocking animations on forms
- Turnstile widget teardown on navigation
- Reduced-motion support on decorative homepage/about assets
