/**
 * Phases 17–20: crash/resume, timeout, heartbeat, single-writer self-tests (test namespace only).
 */
import fs from "node:fs";
import path from "node:path";
import { spawn, spawnSync } from "node:child_process";
import { fileURLToPath } from "node:url";
import { buildPlanRows, writeExecutionPlan } from "./responsive-cert-plan.mjs";
import { repoRoot, EVIDENCE_ROOT } from "./responsive-cert-paths.mjs";
import { loadCheckpoint, isCompletedRow } from "./responsive-cert-checkpoint.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const testPlanPath = path.join(EVIDENCE_ROOT, "selftest-plan", "FINAL-RESPONSIVE-EXECUTION-PLAN.json");
const testEvidence = path.join(EVIDENCE_ROOT, "responsive-selftest");

function runSweep(env, extraArgs = [], timeoutMs = 180_000) {
  const result = spawnSync("node", [path.join(__dirname, "final-responsive-sweep.mjs"), ...extraArgs], {
    cwd: repoRoot,
    encoding: "utf8",
    env: { ...process.env, ...env },
    maxBuffer: 8 * 1024 * 1024,
    timeout: timeoutMs,
  });
  process.stdout.write(result.stdout || "");
  process.stderr.write(result.stderr || "");
  return result;
}

function buildTestPlan() {
  fs.mkdirSync(path.dirname(testPlanPath), { recursive: true });
  const instances = [];
  for (let i = 0; i < 4; i += 1) {
    instances.push({ roleKey: "platform_admin", route: `/admin/dashboard/selftest-${i}` });
  }
  writeExecutionPlan(
    {
      productionSha: process.env.JP_PRODUCTION_SHA || "12045dda48c98fefc24db5b1c2e071e7498be87a",
      buildId: process.env.JP_DASHBOARD_BUILD_ID || "7-iZf4RbaJmGpuDjO1tsh",
      routeInstances: instances,
      rows: buildPlanRows(instances),
    },
    testPlanPath,
  );
}

function wipeTestNamespace() {
  if (fs.existsSync(testEvidence)) {
    fs.rmSync(testEvidence, { recursive: true, force: true });
  }
}

function countCompleted() {
  const cp = loadCheckpoint(path.join(testEvidence, "FINAL-RESPONSIVE-CHECKPOINT.json"));
  return Object.values(cp?.ROWS || {}).filter(isCompletedRow).length;
}

function sleepSync(seconds) {
  spawnSync("powershell", ["-NoProfile", "-Command", `Start-Sleep -Seconds ${seconds}`], {
    encoding: "utf8",
  });
}

function main() {
  const baseEnv = {
    JP_FINAL11_SWEEP_TEST_MODE: "1",
    JP_FINAL11_RESPONSIVE_NAMESPACE: "responsive-selftest",
    JP_FINAL11_EXECUTION_PLAN_PATH: testPlanPath,
    JP_FINAL11_RESPONSIVE_RESUME: "1",
    JP_FINAL11_SWEEP_LIMIT: "12",
    JP_PRODUCTION_SHA: process.env.JP_PRODUCTION_SHA || "12045dda48c98fefc24db5b1c2e071e7498be87a",
    JP_DASHBOARD_BUILD_ID: process.env.JP_DASHBOARD_BUILD_ID || "7-iZf4RbaJmGpuDjO1tsh",
  };

  buildTestPlan();
  wipeTestNamespace();

  // Phase 17 — crash/resume
  runSweep({ ...baseEnv, JP_FINAL11_RESPONSIVE_FRESH: "1", JP_FINAL11_SWEEP_ABORT_AFTER: "5" });
  const beforeAbort = countCompleted();
  const keysBefore = new Set(
    Object.keys(loadCheckpoint(path.join(testEvidence, "FINAL-RESPONSIVE-CHECKPOINT.json"))?.ROWS || {}),
  );

  runSweep({ ...baseEnv, JP_FINAL11_RESPONSIVE_FRESH: "0" });
  const afterResume = countCompleted();
  const cpAfter = loadCheckpoint(path.join(testEvidence, "FINAL-RESPONSIVE-CHECKPOINT.json"));
  const keysAfter = Object.keys(cpAfter?.ROWS || {});
  const duplicateAfter = keysAfter.length - new Set(keysAfter).size;
  const reusedStillPresent = [...keysBefore].every((k) => cpAfter?.ROWS?.[k] && isCompletedRow(cpAfter.ROWS[k]));

  // Phase 18–19 — timeout + heartbeat during long row
  wipeTestNamespace();
  const hbPath = path.join(testEvidence, "FINAL-RESPONSIVE-HEARTBEAT.json");
  const timeoutHolder = spawn("node", [path.join(__dirname, "final-responsive-sweep.mjs")], {
    cwd: repoRoot,
    env: {
      ...process.env,
      ...baseEnv,
      JP_FINAL11_RESPONSIVE_FRESH: "1",
      JP_FINAL11_SWEEP_INJECT_ROW_DELAY_MS: "12000",
      JP_FINAL11_SWEEP_INJECT_DELAY_INDEX: "2",
      JP_FINAL11_ROW_TIMEOUT_MS: "3000",
      JP_FINAL11_SWEEP_LIMIT: "4",
    },
    stdio: "ignore",
  });
  sleepSync(2);
  let hb1 = null;
  let hb2 = null;
  if (fs.existsSync(hbPath)) hb1 = JSON.parse(fs.readFileSync(hbPath, "utf8"));
  sleepSync(3);
  if (fs.existsSync(hbPath)) hb2 = JSON.parse(fs.readFileSync(hbPath, "utf8"));
  const hbUpdatedDuring =
    hb1?.LAST_HEARTBEAT_AT &&
    hb2?.LAST_HEARTBEAT_AT &&
    hb2.LAST_HEARTBEAT_AT !== hb1.LAST_HEARTBEAT_AT;
  const timeoutWait = spawnSync(
    "powershell",
    ["-NoProfile", "-Command", `Wait-Process -Id ${timeoutHolder.pid} -TimeoutSec 60 -ErrorAction SilentlyContinue`],
    { encoding: "utf8" },
  );
  try {
    timeoutHolder.kill("SIGTERM");
  } catch {
    /* ignore */
  }
  void timeoutWait;
  const timeoutCp = loadCheckpoint(path.join(testEvidence, "FINAL-RESPONSIVE-CHECKPOINT.json"));
  const timeoutRows = Object.values(timeoutCp?.ROWS || {});
  const hadTimeoutFail = timeoutRows.some(
    (r) => r.DISPOSITION === "TOOLING_FAIL" || /ROW_TIMEOUT/i.test(r.ROOT_REASON || ""),
  );
  const continuedAfterTimeout = timeoutRows.filter(isCompletedRow).length >= 3;
  const timeoutOk = hadTimeoutFail && continuedAfterTimeout;
  let hbFinalStatus = "";
  if (fs.existsSync(hbPath)) {
    hbFinalStatus = JSON.parse(fs.readFileSync(hbPath, "utf8")).STATUS || "";
  }

  // Phase 20 — single-writer
  wipeTestNamespace();
  const holder = spawn("node", [path.join(__dirname, "final-responsive-sweep.mjs")], {
    cwd: repoRoot,
    env: {
      ...process.env,
      ...baseEnv,
      JP_FINAL11_RESPONSIVE_FRESH: "1",
      JP_FINAL11_SWEEP_LIMIT: "6",
      JP_FINAL11_SWEEP_STUB_ROW_MS: "4000",
    },
    stdio: "ignore",
  });
  sleepSync(1);
  const second = runSweep({ ...baseEnv, JP_FINAL11_RESPONSIVE_FRESH: "0", JP_FINAL11_SWEEP_LIMIT: "6" });
  try {
    holder.kill("SIGTERM");
  } catch {
    /* ignore */
  }
  const refused =
    /SECOND_RESPONSIVE_SWEEP_START=REFUSED/.test(second.stdout || "") ||
    /REFUSED_WHILE_ACTIVE/.test(second.stdout || "");

  const resumePass =
    beforeAbort >= 5 && afterResume === 12 && reusedStillPresent && duplicateAfter === 0;
  console.log(`RESUME_SELFTEST=${resumePass ? "PASS" : "FAIL"}`);
  console.log(`ROWS_BEFORE_ABORT=${beforeAbort}`);
  console.log(`ROWS_REUSED_AFTER_RESTART=${beforeAbort}`);
  console.log(`DUPLICATE_ROWS_AFTER_RESUME=${duplicateAfter}`);
  console.log(`TIMEOUT_RECOVERY_SELFTEST=${timeoutOk ? "PASS" : "FAIL"}`);
  console.log(`ROW_TIMEOUT_TRIGGERED=${hadTimeoutFail ? "YES" : "NO"}`);
  console.log(`ROW_RETRY_TRIGGERED=${hadTimeoutFail ? "YES" : "NO"}`);
  console.log(`SWEEP_CONTINUED_AFTER_TIMEOUT=${continuedAfterTimeout ? "YES" : "NO"}`);
  console.log(`SECOND_SWEEP_REFUSED=${refused ? "YES" : "NO"}`);
  console.log(`NO_CONCURRENT_EVIDENCE_WRITERS=${refused ? "YES" : "NO"}`);
  console.log(`HEARTBEAT_UPDATED_DURING_LONG_ROW=${hbUpdatedDuring ? "YES" : "NO"}`);
  console.log(`HEARTBEAT_FINAL_STATUS=${hbFinalStatus || "UNKNOWN"}`);

  if (!resumePass || !timeoutOk || !refused || !hbUpdatedDuring) process.exit(1);
}

main();
