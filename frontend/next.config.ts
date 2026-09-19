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
  experimental: {
    // Keep soft-nav Flight payloads warm longer within a tab (cert N samples / real browsing).
    staleTimes: {
      dynamic: 60,
      static: 300,
    },
  },
  async redirects() {
    return [
      {
        source: "/contact",
        destination: "/about-us",
        permanent: true,
      },
    ];
  },
  async rewrites() {
    return [
      {
        source: "/__dev/jetpk-theme-lab",
        destination: "/dev/jetpk-theme-lab",
      },
      {
        // OLS on :8088 routes bare /booking/* to the public Next shell (308), so the
        // Next → Laravel rewrite must hit Laravel's front controller explicitly.
        source: "/laravel/:path*",
        destination: `${laravelProxyTarget}/index.php/:path*`,
      },
    ];
  },
};

export default nextConfig;
