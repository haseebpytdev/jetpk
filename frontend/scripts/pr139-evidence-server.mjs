/**
 * Next production server for PR139 responsive-media evidence.
 * Forces LARAVEL_URL to the local mock API and disables CMS fixtures.
 */
import { existsSync, rmSync } from "node:fs";
import { spawn } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const frontendRoot = path.resolve(__dirname, "..");
const buildIdPath = path.join(frontendRoot, ".next", "BUILD_ID");
const cacheDir = path.join(frontendRoot, ".next", "cache");
const port = process.env.PLAYWRIGHT_PORT ?? "3019";
const mockPort = process.env.PR139_MOCK_API_PORT ?? "18080";
const laravelUrl = process.env.LARAVEL_URL ?? `http://127.0.0.1:${mockPort}`;

if (!existsSync(buildIdPath)) {
  console.error("[pr139-evidence-server] Missing .next/BUILD_ID. Run npm run build first.");
  process.exit(1);
}

// Drop build-time unstable_cache homepage entries so the mock CMS payload is used.
try {
  rmSync(cacheDir, { recursive: true, force: true });
  console.log("[pr139-evidence-server] cleared .next/cache");
} catch {
  // ignore
}

console.log(`[pr139-evidence-server] LARAVEL_URL=${laravelUrl} port=${port}`);

const child = spawn("npx", ["next", "start", "-H", "127.0.0.1", "-p", port], {
  cwd: frontendRoot,
  stdio: "inherit",
  shell: true,
  env: {
    ...process.env,
    NODE_ENV: "production",
    PLAYWRIGHT_PORT: port,
    LARAVEL_URL: laravelUrl,
    NEXT_PUBLIC_LARAVEL_URL: laravelUrl,
    OTA_ALLOW_CONTENT_FIXTURE: "false",
    NEXT_PUBLIC_ALLOW_CONTENT_FIXTURES: "false",
    OTA_ALLOW_SESSION_FIXTURE: "true",
    NEXT_PUBLIC_SESSION_PREVIEW: "logged-out",
  },
});

child.on("exit", (code, signal) => {
  if (signal) process.kill(process.pid, signal);
  else process.exit(code ?? 0);
});

process.on("SIGINT", () => child.kill("SIGINT"));
process.on("SIGTERM", () => child.kill("SIGTERM"));
