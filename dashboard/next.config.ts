import type { NextConfig } from "next";
import path from "path";

/**
 * CORRECTION-08: OLS routes bare `/_next/*` to the public Next app (:3010).
 * Dashboard assets must be emitted under `/dashboard-next/_next/*` so the
 * existing rewrite can reach jetpk-dashboard (:3001). Env is set in
 * `.env.production.local` as DASHBOARD_ASSET_PREFIX=/dashboard-next.
 */
const assetPrefix = (process.env.DASHBOARD_ASSET_PREFIX || "").replace(/\/$/, "") || undefined;

const nextConfig: NextConfig = {
  reactStrictMode: true,
  poweredByHeader: false,
  outputFileTracingRoot: path.join(__dirname),
  ...(assetPrefix ? { assetPrefix } : {}),
};

export default nextConfig;
