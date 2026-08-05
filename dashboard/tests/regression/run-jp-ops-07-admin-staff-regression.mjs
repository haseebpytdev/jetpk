/**
 * JP-OPS-07 regression runner — runtime linkage + operational + connected mutation Playwright.
 */

import { spawnSync } from "node:child_process";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const root = join(dirname(fileURLToPath(import.meta.url)), "..", "..");

function run(cmd, args, options = {}) {
  const result = spawnSync(cmd, args, { cwd: root, encoding: "utf8", shell: true, ...options });
  if (result.stdout) process.stdout.write(result.stdout);
  if (result.stderr) process.stderr.write(result.stderr);
  return result.status ?? 1;
}

function buildDashboard(env = {}) {
  return run("npm", ["run", "build"], {
    env: { ...process.env, ...env },
  });
}

let exit = 0;

exit = run("node", ["tests/regression/jp-ops-07-runtime-linkage.test.mjs"]) || exit;

exit = buildDashboard({
  NEXT_PUBLIC_DASHBOARD_MODE: "live",
  NEXT_PUBLIC_USE_MOCK_DATA: "true",
}) || exit;

exit = run("npx", ["playwright", "test", "tests/jp-ops-07-admin-staff-operational.spec.ts"]) || exit;

exit = run("npx", ["playwright", "test", "tests/jp-ops-07-connected-mutations.spec.ts"]) || exit;

console.log(`jp-ops-07-admin-staff-regression exit=${exit}`);
process.exit(exit);
