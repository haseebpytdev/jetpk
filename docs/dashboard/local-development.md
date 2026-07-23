# Local Development — `/testdash`

## Prerequisites

- Node.js 20+ (LTS recommended)
- npm

## Setup

```bash
cd dashboard
cp .env.example .env.local
npm install
npm run dev
```

Open: **http://localhost:3001/testdash**

(Laravel is **not** required for the preview UI in DASH-01.)

## Scripts

| Command | Purpose |
|---------|---------|
| `npm run dev` | Dev server on port **3001** |
| `npm run build` | Production build with `basePath` |
| `npm run start` | Serve production build on 3001 |
| `npm run lint` | ESLint (Next) |
| `npm run typecheck` | `tsc --noEmit` |
| `npm run test:smoke` | Playwright overview + planned stub (dev server must be running) |

## Smoke tests

Playwright uses production `next start` on port **3002** (`npm run test:smoke` runs `build` first). Dev UI stays on **3001**.

```bash
npx playwright install chromium   # once per machine
npm run test:smoke
```

Playwright `baseURL` is `http://127.0.0.1:3002`; tests navigate to `/testdash` (do not set `baseURL` to include `/testdash` — `page.goto("/")` would miss the Next `basePath`).


See [`dashboard/.env.example`](../../dashboard/.env.example):

- `NEXT_PUBLIC_DASHBOARD_MODE=preview`
- `NEXT_PUBLIC_USE_MOCK_DATA=true`
- `NEXT_PUBLIC_ALLOW_MUTATIONS=false`

## Laravel coexistence

Run Laravel separately on `:8000` if needed for legacy `/admin` QA. No proxy between apps in this phase.
