import type { NextConfig } from "next";
import path from "path";

const laravelProxyTarget = (process.env.LARAVEL_URL ?? process.env.NEXT_PUBLIC_LARAVEL_URL ?? "http://127.0.0.1:8000").replace(
  /\/$/,
  "",
);

const nextConfig: NextConfig = {
  reactStrictMode: true,
  poweredByHeader: false,
  outputFileTracingRoot: path.join(__dirname),
  async rewrites() {
    return [
      {
        source: "/__dev/jetpk-theme-lab",
        destination: "/dev/jetpk-theme-lab",
      },
      {
        source: "/__dev/jetpk-homepage-v2",
        destination: "/dev/jetpk-homepage-v2",
      },
      {
        source: "/laravel/:path*",
        destination: `${laravelProxyTarget}/:path*`,
      },
    ];
  },
};

export default nextConfig;
