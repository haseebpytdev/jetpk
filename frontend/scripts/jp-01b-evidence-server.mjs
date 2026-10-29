/**
 * Next production server for JP-UI-PERF-01B first-paint branding evidence.
 * Points LARAVEL_URL at the local branding mock API and clears .next/cache.
 */
import { existsSync, rmSync } from "node:fs";
import { spawn } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const frontendRoot = path.resolve(__dirname, "..");
const buildIdPath = path.join(frontendRoot, ".next", "BUILD_ID");
const cacheDir = path.join(frontendRoot, ".next", "cache");
const port = process.env.PLAYWRIGHT_PORT ?? "3021";
const mockPort = process.env.JP_01B_MOCK_API_PORT ?? "18081";
const laravelUrl = process.env.LARAVEL_URL ?? `http://127.0.0.1:${mockPort}`;

if (!existsSync(buildIdPath)) {
  console.error("[jp-01b-evidence-server] Missing .next/BUILD_ID. Run npm run build first.");
  process.exit(1);
}

try {
  rmSync(cacheDir, { recursive: true, force: true });
  console.log("[jp-01b-evidence-server] cleared .next/cache");
} catch {
  // ignore
}

console.log(`[jp-01b-evidence-server] LARAVEL_URL=${laravelUrl} port=${port}`);

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
