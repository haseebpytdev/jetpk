# JP-UI-05A — Profile Menu and Logout QA

## Test file

`frontend/tests/jp-ui-05a-profile-logout.spec.ts`

## Command

```bash
cd frontend
npm run build
npx playwright test tests/jp-ui-05a-profile-logout.spec.ts -c playwright.config.ts
```

## Results (JP-UI-05A)

| Test | Result |
|------|--------|
| Public account menu opens by keyboard; shows identity | Pass |
| Logout POST to `/laravel/logout` with `X-XSRF-TOKEN` | Pass |
| Customer portal sidebar sign-out link `href=/laravel/logout` | Pass |

## Notes

- Session fixture cookie drives SSR authenticated header on `/`
- Logout test verifies authoritative POST + CSRF header (session fixture persists in test env, so full redirect-to-login is not asserted after assign — Laravel session clear is production behavior)
- Keyboard: Enter opens menu; Escape closes; focus on trigger
- Dashboard profile: header/sidebar dropdown from session; no fake notification count
