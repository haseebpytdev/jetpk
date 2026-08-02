import { spawnSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../..");

const suites = [
  "tests/regression/jp-ops-04-agent-regression.test.mjs",
  "tests/regression/jp-ops-04-agent-api-errors.test.mjs",
  "tests/regression/jp-ops-04-agent-mutations.test.mjs",
  "tests/regression/jp-ops-04-csrf-agent-mutations.test.mjs",
];

let failed = false;

for (const suite of suites) {
  const result = spawnSync(process.execPath, ["--test", path.join(root, suite)], {
    cwd: root,
    stdio: "inherit",
    env: process.env,
  });
  if (result.status !== 0) failed = true;
}

process.exit(failed ? 1 : 0);
