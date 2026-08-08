import type { NextConfig } from "next";
import path from "path";
import { resolveDashboardAssetPrefix } from "./lib/dashboard-asset-prefix";

const dashboardAssetPrefix = resolveDashboardAssetPrefix();

const nextConfig: NextConfig = {
  reactStrictMode: true,
  poweredByHeader: false,
  outputFileTracingRoot: path.join(__dirname),
  ...(dashboardAssetPrefix ? { assetPrefix: dashboardAssetPrefix } : {}),
};

export default nextConfig;
