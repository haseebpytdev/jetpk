import { spawnSync } from "node:child_process";
import { existsSync } from "node:fs";
import { fileURLToPath } from "node:url";
import path from "node:path";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, "../..");

const suites = [
  "tests/regression/jp-ops-05-runtime-linkage.test.mjs",
  "tests/regression/jp-ops-05-api-errors.test.mjs",
  "tests/regression/jp-ops-05-csrf-replay.test.mjs",
  "tests/jp-ops-05-admin-staff-regression.spec.ts",
];

let failed = false;

const buildIdPath = path.join(root, ".next", "BUILD_ID");
if (!existsSync(buildIdPath)) {
  const build = spawnSync("npm", ["run", "build"], { cwd: root, stdio: "inherit", shell: true });
  if (build.status !== 0) {
    process.exit(build.status ?? 1);
  }
}

for (const suite of suites) {
  const isPlaywright = suite.endsWith(".spec.ts");
  const result = isPlaywright
    ? spawnSync("npx", ["playwright", "test", suite], { cwd: root, stdio: "inherit", shell: true })
    : spawnSync(process.execPath, ["--experimental-strip-types", path.join(root, suite)], {
        cwd: root,
        stdio: "inherit",
      });
  if (result.status !== 0) failed = true;
}

process.exit(failed ? 1 : 0);
