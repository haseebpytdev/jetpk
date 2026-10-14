/**
 * Orchestrates FINAL-11 role-matrix + isolation + discovery checkpoint (tooling only).
 */
import { spawnSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");

function runNode(script, extraEnv = {}) {
  const result = spawnSync("node", [path.join(__dirname, script)], {
    cwd: repoRoot,
    encoding: "utf8",
    env: { ...process.env, ...extraEnv },
    maxBuffer: 16 * 1024 * 1024,
  });
  process.stdout.write(result.stdout || "");
  process.stderr.write(result.stderr || "");
  return result.status ?? 1;
}

const steps = [
  ["build-auth-states.mjs", {}],
  ["run-agent-staff-rbac-cert.mjs", {}],
  ["run-legacy-agency-admin-cert.mjs", {}],
  ["run-isolation-cert.mjs", {}],
  [
    "run-production-discovery.mjs",
    {
      JP_PRODUCTION_SHA: process.env.JP_PRODUCTION_SHA,
      JP_DASHBOARD_BUILD_ID: process.env.JP_DASHBOARD_BUILD_ID,
    },
  ],
];

for (const [script, env] of steps) {
  console.log(`CHECKPOINT_STEP=${script}`);
  const code = runNode(script, env);
  if (code !== 0) {
    console.log(`CHECKPOINT_FAIL=${script}`);
    process.exit(code);
  }
}

console.log("CHECKPOINT_WAVE=COMPLETE");
