/**
 * Ensures a healthy Laravel listener exists before Playwright smoke runs.
 * Starts php artisan serve only when :8000 is not already accepting requests.
 */
import { spawn } from "node:child_process";
import http from "node:http";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "..", "..");
const host = "127.0.0.1";
const port = Number(process.env.LARAVEL_PORT ?? "8000");

function probe(pathname, timeoutMs = 15000) {
  return new Promise((resolve) => {
    const request = http.get(
      {
        host,
        port,
        path: pathname,
        timeout: timeoutMs,
      },
      (response) => {
        response.resume();
        resolve(typeof response.statusCode === "number" && response.statusCode < 500);
      },
    );

    request.on("timeout", () => {
      request.destroy();
      resolve(false);
    });
    request.on("error", () => resolve(false));
  });
}

async function waitForLaravel(maxAttempts = 90) {
  for (let attempt = 0; attempt < maxAttempts; attempt += 1) {
    if (await probe("/")) {
      if (await probe("/index.php/api/public/content/csrf-token")) {
        await probe("/index.php/groups/search/facets");
        return true;
      }
    }
    await new Promise((resolve) => setTimeout(resolve, 2000));
  }
  return false;
}

export default async function globalSetup() {
  if (await probe("/index.php/api/public/content/csrf-token")) {
    process.env.PLAYWRIGHT_LARAVEL_STARTED = "0";
    return;
  }

  const child = spawn("php", ["artisan", "serve", `--host=${host}`, `--port=${port}`], {
    cwd: repoRoot,
    detached: true,
    stdio: "ignore",
    shell: process.platform === "win32",
    env: {
      ...process.env,
      APP_URL: `http://${host}:${port}`,
    },
  });

  child.unref();
  process.env.PLAYWRIGHT_LARAVEL_PID = String(child.pid ?? "");
  process.env.PLAYWRIGHT_LARAVEL_STARTED = "1";

  const ready = await waitForLaravel();
  if (!ready) {
    throw new Error(
      `[playwright-laravel-setup] Laravel did not become ready at http://${host}:${port}`,
    );
  }
}
