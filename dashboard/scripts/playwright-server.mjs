/**
 * Production Next.js server for dashboard Playwright smoke tests.
 * Requires `npm run build` before Playwright starts.
 */
import { existsSync } from "node:fs";
import { spawn } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const dashboardRoot = path.resolve(__dirname, "..");
const buildIdPath = path.join(dashboardRoot, ".next", "BUILD_ID");
const port = process.env.PLAYWRIGHT_PORT ?? "3003";

if (!existsSync(buildIdPath)) {
  console.error(
    "[dashboard-playwright-server] Missing production build (.next/BUILD_ID). Run `npm run build` before Playwright.",
  );
  process.exit(1);
}

const child = spawn("npx", ["next", "start", "-H", "127.0.0.1", "-p", port], {
  cwd: dashboardRoot,
  stdio: "inherit",
  shell: true,
  env: { ...process.env, NODE_ENV: "production", PORT: port },
});

child.on("error", (error) => {
  console.error("[dashboard-playwright-server] Failed to start Next.js:", error);
  process.exit(1);
});

child.on("exit", (code, signal) => {
  if (signal) {
    process.kill(process.pid, signal);
    return;
  }
  process.exit(code ?? 0);
});

process.on("SIGINT", () => child.kill("SIGINT"));
process.on("SIGTERM", () => child.kill("SIGTERM"));
