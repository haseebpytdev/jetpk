/**
 * Wave 01 orchestrator: normalize matrix, probe auth, run live cert, summarize.
 */
import { spawnSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");

function run(script, extraEnv = {}) {
  const result = spawnSync("node", [path.join(__dirname, script)], {
    cwd: repoRoot,
    encoding: "utf8",
    env: { ...process.env, ...extraEnv },
    maxBuffer: 32 * 1024 * 1024,
  });
  process.stdout.write(result.stdout || "");
  process.stderr.write(result.stderr || "");
  return result.status ?? 1;
}

const steps = [
  ["build-functional-case-matrix.mjs", {}],
  ["probe-auth-states.mjs", {}],
  ["run-functional-cert-wave.mjs", { JP_FUNCTIONAL_CERT_RESUME: process.env.JP_FUNCTIONAL_CERT_RESUME ?? "1" }],
  ["summarize-functional-cert-checkpoint.mjs", {}],
];

for (const [script, env] of steps) {
  console.log(`WAVE01_STEP=${script}`);
  const code = run(script, env);
  if (code !== 0 && script === "probe-auth-states.mjs") {
    console.log("WAVE01_AUTH_PARTIAL=continuing_with_existing_storage_states");
    continue;
  }
  if (code !== 0) {
    console.log(`WAVE01_FAIL=${script}`);
    process.exit(code);
  }
}

console.log("WAVE01_ORCHESTRATOR=COMPLETE");
