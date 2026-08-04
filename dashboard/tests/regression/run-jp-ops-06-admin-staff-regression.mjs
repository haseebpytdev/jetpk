import { spawnSync } from "node:child_process";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const dashboardRoot = join(dirname(fileURLToPath(import.meta.url)), "..", "..");

const nodeChecks = ["tests/regression/jp-ops-06-runtime-linkage.test.mjs"];

let failed = 0;

for (const script of nodeChecks) {
  const result = spawnSync(process.execPath, [join(dashboardRoot, script)], {
    cwd: dashboardRoot,
    stdio: "inherit",
    shell: false,
  });
  if (result.status !== 0) failed += 1;
}

const playwright = spawnSync("npx", ["playwright", "test", "tests/jp-ops-06-admin-staff-operational.spec.ts"], {
  cwd: dashboardRoot,
  stdio: "inherit",
  shell: true,
});
if (playwright.status !== 0) failed += 1;

process.exit(failed > 0 ? 1 : 0);
