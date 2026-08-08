/**
 * Dashboard Next static asset namespace (Release-02A).
 *
 * When set (e.g. `/dashboard-next`), Next.js `assetPrefix` emits dashboard
 * build assets under `{prefix}/_next/*` while page routes remain at
 * `/admin/dashboard` and `/staff/dashboard`.
 *
 * Server/build-time only — not exposed to the browser for Laravel API calls.
 */
export function normalizeDashboardAssetPrefix(value: string | undefined): string | undefined {
  if (!value) {
    return undefined;
  }

  const trimmed = value.trim();
  if (!trimmed) {
    return undefined;
  }

  return trimmed.endsWith("/") ? trimmed.slice(0, -1) : trimmed;
}

export function resolveDashboardAssetPrefix(): string | undefined {
  return normalizeDashboardAssetPrefix(process.env.DASHBOARD_ASSET_PREFIX);
}

export const DASHBOARD_ASSET_PREFIX_DEFAULT = "/dashboard-next";
