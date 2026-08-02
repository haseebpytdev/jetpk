import { spawnSync } from "node:child_process";
import { fileURLToPath } from "node:url";
import path from "node:path";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, "../..");

const suites = [
  "tests/regression/jp-ops-02-runtime-linkage.test.mjs",
  "tests/regression/jp-ops-02-api-errors.test.mjs",
  "tests/regression/jp-ops-02-csrf-replay.test.mjs",
];

let failed = false;

for (const suite of suites) {
  const result = spawnSync(process.execPath, [path.join(root, suite)], {
    cwd: root,
    stdio: "inherit",
  });
  if (result.status !== 0) failed = true;
}

process.exit(failed ? 1 : 0);
