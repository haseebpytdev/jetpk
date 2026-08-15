/** Public browser origin — never loopback defaults in client bundles. */
const PUBLIC_ORIGIN =
  process.env.NEXT_PUBLIC_APP_URL?.replace(/\/$/, "") || "https://jetpakistan.pk";

/**
 * Browser-facing Laravel origin for absolute URL helpers (handoff, robots).
 * Production uses same public origin; local dev sets NEXT_PUBLIC_LARAVEL_URL in .env.
 */
const LARAVEL_PUBLIC_ORIGIN =
  process.env.NEXT_PUBLIC_LARAVEL_URL?.replace(/\/$/, "") || PUBLIC_ORIGIN;

export const appConfig = {
  appUrl: PUBLIC_ORIGIN,
  laravelUrl: LARAVEL_PUBLIC_ORIGIN,
  defaultCurrency: process.env.NEXT_PUBLIC_DEFAULT_CURRENCY ?? "PKR",
  sessionPreview: process.env.NEXT_PUBLIC_SESSION_PREVIEW ?? "logged-out",
} as const;

export type AppConfig = typeof appConfig;
