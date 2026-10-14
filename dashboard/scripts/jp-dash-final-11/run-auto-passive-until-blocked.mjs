/**
 * Run up to N foreground auto-passive batches; triage on FAIL, continue if clean.
 */
import { spawnSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");
const maxBatches = Number.parseInt(process.env.JP_AUTO_PASSIVE_MAX_BATCHES ?? "5", 10);

function runNode(script, env = {}) {
  const r = spawnSync("node", [script], {
    cwd: repoRoot,
    env: { ...process.env, ...env, JP_FUNCTIONAL_CERT_MAX_GROUPS: "" },
    encoding: "utf8",
    maxBuffer: 20 * 1024 * 1024,
  });
  const out = `${r.stdout || ""}${r.stderr || ""}`;
  process.stdout.write(out);
  return { status: r.status ?? 1, out };
}

function parseMetric(out, key) {
  const m = out.match(new RegExp(`${key}=(\\d+)`));
  return m ? Number.parseInt(m[1], 10) : null;
}

for (let i = 0; i < maxBatches; i += 1) {
  runNode("dashboard/scripts/jp-dash-final-11/reset-cert-tooling-failed-cases.mjs");
  runNode("dashboard/scripts/jp-dash-final-11/reset-weak-search-filter-passes.mjs");
  const wave = runNode("dashboard/scripts/jp-dash-final-11/run-functional-cert-wave.mjs", {
    JP_FUNCTIONAL_CERT_RESUME: "1",
    JP_FUNCTIONAL_CERT_BATCH_GROUPS: "30",
  });
  if (wave.status !== 0) {
    console.log(`AUTO_PASSIVE_LOOP_STOP=runner_exit_${wave.status}`);
    break;
  }

  const fail = parseMetric(wave.out, "BATCH_CASES_FAIL") ?? 0;
  if (fail > 0) {
    const triage = runNode("dashboard/scripts/jp-dash-final-11/triage-fail-candidates.mjs");
    const appDefects = parseMetric(triage.out, "CONFIRMED_APPLICATION_DEFECTS") ?? 0;
    if (appDefects > 0) {
      console.log("AUTO_PASSIVE_LOOP_STOP=application_defect");
      break;
    }
    const sumAfterTriage = runNode("dashboard/scripts/jp-dash-final-11/summarize-functional-cert-checkpoint.mjs");
    const failCandidates = parseMetric(sumAfterTriage.out, "FUNCTIONAL_CASES_FAIL_CANDIDATES") ?? 0;
    if (failCandidates > 0) {
      const resetTooling = runNode("dashboard/scripts/jp-dash-final-11/reset-cert-tooling-failed-cases.mjs");
      const failAfterReset = parseMetric(resetTooling.out, "FUNCTIONAL_CASES_FAIL") ?? failCandidates;
      if (failAfterReset > 0) {
        console.log("AUTO_PASSIVE_LOOP_STOP=unresolved_fail_candidates");
        break;
      }
      console.log(`AUTO_PASSIVE_LOOP_TOOLING_RESET batch=${i + 1}`);
    }
    console.log(`AUTO_PASSIVE_LOOP_TRIAGE_RECOVERED batch=${i + 1} transient_or_tooling=${fail}`);
  }

  const sum = runNode("dashboard/scripts/jp-dash-final-11/summarize-functional-cert-checkpoint.mjs");
  const untested = parseMetric(sum.out, "UNTESTED_AUTO_PASSIVE_CASES");
  if (untested === 0) {
    console.log("AUTO_PASSIVE_LOOP_COMPLETE=YES");
    break;
  }
  console.log(`AUTO_PASSIVE_LOOP_BATCH=${i + 1} UNTESTED_AUTO_PASSIVE_CASES=${untested}`);
}
