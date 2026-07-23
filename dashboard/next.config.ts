import type { NextConfig } from "next";
import path from "path";

const nextConfig: NextConfig = {
  basePath: "/testdash",
  reactStrictMode: true,
  poweredByHeader: false,
  outputFileTracingRoot: path.join(__dirname),
};

export default nextConfig;
