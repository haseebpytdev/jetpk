# 03 — Favicon evidence

Date: 2026-09-16  
Scope: FINAL GOLDEN CLOSURE — JetPakistan favicon only (no deploy / no perf sweep)

## Approved asset

Source of truth: `public/client-assets/jetpk/favicon/favicon.ico` (251-byte JetPakistan ICO).

Copied / present at:

| Path | Size | Role |
|------|------|------|
| `frontend/app/favicon.ico` | 251 B | Next App Router file convention |
| `frontend/public/favicon.ico` | 251 B | Static public serve |
| `public/favicon.ico` | 251 B | Laravel/public root |
| `frontend/app/icon.png` | 229 B | Optional PNG from favicon-32 (App Router `icon`) |

All sizes > 0.

## Metadata

`frontend/app/layout.tsx` now sets:

```ts
icons: {
  icon: [{ url: "/favicon.ico" }],
  shortcut: [{ url: "/favicon.ico" }],
}
```

Together with `app/favicon.ico`, this prefers the JetPakistan asset over any default Next mark.

## Regression

```bash
node frontend/tests/regression/jp-favicon-fab-closure.test.mjs
```

Asserts: three favicon paths exist with size > 0; layout declares `/favicon.ico` icons.

## Residual gaps

- Live HTML `<link rel="icon">` not probed against a running Next process in this pass.
- Golden worktree also supports CMS `favicon_url` via `generateMetadata`; current tree uses static `/favicon.ico` only (approved asset). Wire CMS URL later if production branding requires cache-busted remote favicons.
- Perf notes intentionally out of scope for this file (favicon-only closure slice).
