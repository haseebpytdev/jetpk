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
      // Route through /index.php so OLS Next SPA rewrite rules for /agent/*
      // and /customer/* cannot steal JSON API calls on the private Laravel
      // listener (127.0.0.1:8088). Browser paths remain /laravel/:path*.
      {
        source: "/laravel/:path*",
        destination: `${laravelProxyTarget}/index.php/:path*`,
      },
    ];
  },
};

export default nextConfig;
