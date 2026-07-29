/**
 * Production Next.js server for Playwright smoke tests.
 * Requires `npm run build` before Playwright starts (enforced by test:* scripts).
 */
import { existsSync } from "node:fs";
import { spawn } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const frontendRoot = path.resolve(__dirname, "..");
const buildIdPath = path.join(frontendRoot, ".next", "BUILD_ID");
const port = process.env.PLAYWRIGHT_PORT ?? "3002";

if (!existsSync(buildIdPath)) {
  console.error(
    "[playwright-server] Missing production build (.next/BUILD_ID). Run `npm run build` before Playwright.",
  );
  process.exit(1);
}

const child = spawn("npm", ["run", "start:smoke"], {
  cwd: frontendRoot,
  stdio: "inherit",
  shell: true,
  env: { ...process.env, NODE_ENV: "production", PLAYWRIGHT_PORT: port },
});

child.on("error", (error) => {
  console.error("[playwright-server] Failed to start Next.js:", error);
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
