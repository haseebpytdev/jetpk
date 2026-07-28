#!/usr/bin/env node
/**
 * Production build helper for the back-office dashboard.
 *
 * Runs a standard Next.js production build. Optional static export sync is skipped
 * because module pages rely on searchParams (client hydration). Production serves
 * via `next start` behind Laravel auth proxy — see docs/dashboard/DASHBOARD-PRODUCTION-DEPLOYMENT.md
 */
import { spawnSync } from "node:child_process";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = dirname(fileURLToPath(import.meta.url));
const dashboardRoot = join(__dirname, "..");

const build = spawnSync("npm", ["run", "build"], {
  cwd: dashboardRoot,
  stdio: "inherit",
  shell: true,
});

process.exit(build.status ?? 1);
