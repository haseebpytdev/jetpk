/**
 * JP-PERF-FINAL-02R-CURRENT orchestrator — runs warm Return, Traveler, soft-nav cohorts.
 */
import { spawn } from "child_process";
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));

function runNode(script) {
  return new Promise((resolve, reject) => {
    const child = spawn(process.execPath, [path.join(__dirname, script)], {
      cwd: __dirname,
      stdio: "inherit",
      env: {
        ...process.env,
        JP_RUNTIME_SHA: "f039bef3dda1320c08fdccb4633d5c7c34b3b61e",
        JP_PUBLIC_BUILD_ID: "m_8GEC6BnMkGnfo7ET_Z5v5VXBZjJPRs_a8PUQO8xw",
        JP_PERF_N: process.env.JP_PERF_N || "30",
        JP_NAV_N: process.env.JP_NAV_N || "20",
      },
    });
    child.on("exit", (code) => (code === 0 ? resolve() : reject(new Error(`${script} exit ${code}`))));
  });
}

async function main() {
  const started = new Date().toISOString();
  console.log("JP-PERF-FINAL-02R-CURRENT certification start", started);
  await runNode("run-return-n30.mjs");
  await runNode("run-traveler-n30.mjs");
  await runNode("run-soft-nav-matrix.mjs");
  fs.writeFileSync(
    path.join(__dirname, "certification-run.log"),
    JSON.stringify({ started, finished: new Date().toISOString(), status: "COMPLETE" }, null, 2),
  );
  console.log("CERTIFICATION_RUN_COMPLETE");
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
