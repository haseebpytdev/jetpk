# JetPakistan Public Frontend

Standalone Next.js App Router application for the JetPakistan public website, customer experience, booking flow presentation, and shared Agent dashboard shell.

## Local setup

```bash
cd frontend
cp .env.example .env.local
npm install
```

## Local run

```bash
# Public frontend
npm run dev

# Laravel backend (separate terminal, repo root)
php artisan serve
```

Runtime targets:

- Laravel: `http://127.0.0.1:8000`
- Public frontend: `http://localhost:3000`
- Admin/Staff dashboard: `http://localhost:3001` (`dashboard/`)

## Quality commands

```bash
npm run typecheck
npm run lint
npm run build
npm run test:smoke
```

## Phase scope

JP-FE-01 delivers:

- responsive public shell (header, footer, navigation)
- design token foundation
- fixture session adapter for auth presentation states
- Laravel API client boundary scaffold
- homepage placeholder only

Full homepage, search UI, and booking flow belong to later phases.

## Session preview

Set `NEXT_PUBLIC_SESSION_PREVIEW=logged-in` in `.env.local` to preview the authenticated account menu state without Laravel auth wiring.
