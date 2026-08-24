/**
 * JP-BO-04 Stage A Playwright runner — live Dashboard + mock fixtures.
 */
import { spawnSync } from "node:child_process";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const root = join(dirname(fileURLToPath(import.meta.url)), "..");

function run(cmd, args, options = {}) {
  const result = spawnSync(cmd, args, {
    cwd: root,
    encoding: "utf8",
    shell: true,
    stdio: "inherit",
    ...options,
  });
  return result.status ?? 1;
}

const buildEnv = {
  ...process.env,
  NEXT_PUBLIC_DASHBOARD_MODE: "live",
  NEXT_PUBLIC_USE_MOCK_DATA: "true",
};

console.log("[jp-bo-04] building dashboard (live + mock fixtures)...");
let exit = run("npm", ["run", "build"], { env: buildEnv });
if (exit !== 0) {
  process.exit(exit);
}

console.log("[jp-bo-04] running operational matrix Playwright...");
exit = run("npx", ["playwright", "test", "-c", "playwright.jp-bo-04.config.ts"], {
  env: { ...process.env, PLAYWRIGHT_PORT: process.env.PLAYWRIGHT_PORT ?? "3014" },
});

console.log(`[jp-bo-04] playwright exit=${exit}`);
process.exit(exit);
