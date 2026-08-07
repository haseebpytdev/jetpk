/**
 * Production smoke server for Playwright. Honors PLAYWRIGHT_PORT (default 3002).
 */
import { spawn } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const frontendRoot = path.resolve(__dirname, "..");
const port = process.env.PLAYWRIGHT_PORT ?? "3002";

const smokeEnv = {
  ...process.env,
  NODE_ENV: "production",
  PLAYWRIGHT_PORT: port,
};

// Smoke runs only: enable gated SSR session fixtures for portal Playwright suites.
if (process.env.OTA_ALLOW_SESSION_FIXTURE !== "false") {
  smokeEnv.OTA_ALLOW_SESSION_FIXTURE = process.env.OTA_ALLOW_SESSION_FIXTURE ?? "true";
}

const child = spawn("npx", ["next", "start", "-H", "127.0.0.1", "-p", port], {
  cwd: frontendRoot,
  stdio: "inherit",
  shell: true,
  env: smokeEnv,
});

child.on("exit", (code, signal) => {
  if (signal) process.kill(process.pid, signal);
  else process.exit(code ?? 0);
});

process.on("SIGINT", () => child.kill("SIGINT"));
process.on("SIGTERM", () => child.kill("SIGTERM"));
