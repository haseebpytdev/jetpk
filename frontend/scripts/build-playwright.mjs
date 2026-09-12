/**
 * Production build for Playwright smoke with Laravel/content fixtures enabled.
 */
import { spawnSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const frontendRoot = path.resolve(__dirname, "..");

const result = spawnSync("npm", ["run", "build"], {
  cwd: frontendRoot,
  stdio: "inherit",
  shell: true,
  env: {
    ...process.env,
    OTA_ALLOW_CONTENT_FIXTURE: process.env.OTA_ALLOW_CONTENT_FIXTURE ?? "true",
    LARAVEL_URL: process.env.LARAVEL_URL ?? "http://127.0.0.1:8000",
  },
});

process.exit(result.status ?? 1);
