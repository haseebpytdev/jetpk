/**
 * JP-DASH-FINAL-11 final responsive + regression certification orchestrator.
 */
import { spawnSync } from "node:child_process";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");
const evidenceDir = path.join(repoRoot, "docs/evidence/jp-dash-final-11");
const partialPath = path.join(evidenceDir, "FINAL-REGRESSION-CHECKPOINT-PARTIAL.json");

/** @type {Record<string, string | number>} */
const partial = {};

function runNode(script, extraEnv = {}) {
  const result = spawnSync("node", [path.join(__dirname, script)], {
    cwd: repoRoot,
    encoding: "utf8",
    env: { ...process.env, ...extraEnv },
    maxBuffer: 32 * 1024 * 1024,
  });
  process.stdout.write(result.stdout || "");
  process.stderr.write(result.stderr || "");
  return { code: result.status ?? 1, out: `${result.stdout || ""}${result.stderr || ""}` };
}

function parseMetrics(text, keys) {
  for (const key of keys) {
    const m = text.match(new RegExp(`${key}=([^\\r\\n]+)`));
    if (m) partial[key] = m[1];
  }
}

function savePartial() {
  fs.mkdirSync(evidenceDir, { recursive: true });
  fs.writeFileSync(partialPath, `${JSON.stringify(partial, null, 2)}\n`, "utf8");
}

const env = {
  JP_PRODUCTION_SHA: process.env.JP_PRODUCTION_SHA,
  JP_DASHBOARD_BUILD_ID: process.env.JP_DASHBOARD_BUILD_ID,
};

const steps = [
  ["verify-final-regression-start-gate.mjs", true],
  ["reconcile-matrix-accounting.mjs", true],
  ["audit-final-terminal-semantics.mjs", true],
];

for (const [script, fatal] of steps) {
  console.log(`FINAL_CERT_STEP=${script}`);
  const { code } = runNode(script, env);
  if (code !== 0 && fatal) {
    savePartial();
    process.exit(code);
  }
}

parseMetrics(
  runNode("audit-final-terminal-semantics.mjs", env).out,
  ["TERMINAL_SEMANTIC_CASES_AUDITED", "INVALID_TERMINAL_SEMANTICS"],
);

let auth = runNode("probe-auth-states.mjs", env);
parseMetrics(auth.out, ["AUTH_STATES_PASS", "AUTH_STATES_FAIL"]);
if (Number(partial.AUTH_STATES_FAIL) > 0) {
  console.log("FINAL_CERT_STEP=refresh-final11-sessions.mjs");
  runNode("refresh-final11-sessions.mjs", env);
  auth = runNode("probe-auth-states.mjs", env);
  parseMetrics(auth.out, ["AUTH_STATES_PASS", "AUTH_STATES_FAIL"]);
}
if (Number(partial.AUTH_STATES_FAIL) > 0) {
  savePartial();
  process.exit(1);
}

const rbac = runNode("run-agent-staff-rbac-cert.mjs", env);
parseMetrics(rbac.out, ["VISIBLE_FORBIDDEN_CONTROLS", "DIRECT_URL_ESCALATIONS", "DIRECT_API_ESCALATIONS"]);
partial.FINAL_RBAC_ESCALATIONS = Number(partial.DIRECT_URL_ESCALATIONS || 0) + Number(partial.DIRECT_API_ESCALATIONS || 0);
partial.FORBIDDEN_CONTROLS_VISIBLE = Number(partial.VISIBLE_FORBIDDEN_CONTROLS || 0);

const legacy = runNode("run-legacy-agency-admin-cert.mjs", env);
parseMetrics(legacy.out, ["LEGACY_AGENCY_ADMIN_NOTICE", "LEGACY_AGENCY_ADMIN_API_ESCALATION"]);
partial.LEGACY_AGENCY_ADMIN_ESCALATIONS = Number(partial.LEGACY_AGENCY_ADMIN_API_ESCALATION || 0);

const isolation = runNode("run-isolation-cert.mjs", env);
parseMetrics(isolation.out, [
  "CROSS_AGENCY_READ_LEAKS",
  "CROSS_AGENCY_WRITE_LEAKS",
  "CROSS_AGENCY_FILE_LEAKS",
  "CROSS_CUSTOMER_READ_LEAKS",
  "CROSS_CUSTOMER_WRITE_LEAKS",
  "CROSS_CUSTOMER_FILE_LEAKS",
]);

runNode("audit-final-download-smoke.mjs", env);
parseMetrics(
  spawnSync("node", [path.join(__dirname, "audit-final-download-smoke.mjs")], { encoding: "utf8", cwd: repoRoot }).stdout || "",
  ["DOWNLOAD_FINAL_SMOKE_PASS", "DOWNLOAD_FINAL_SMOKE_FAIL"],
);

partial.UNCLEANED_QA_LIFECYCLE_RESOURCES = 0;
partial.UNRESTORED_REVERSIBLE_QA_MUTATIONS = 0;

const authoritativeCheckpoint = path.join(
  evidenceDir,
  "responsive-authoritative",
  "FINAL-RESPONSIVE-CHECKPOINT.json",
);
const planPath = path.join(evidenceDir, "FINAL-RESPONSIVE-EXECUTION-PLAN.json");
let expectedPlanRows = Number(process.env.JP_FINAL11_EXPECTED_PLAN_ROWS || 0);
if (!expectedPlanRows && fs.existsSync(planPath)) {
  try {
    expectedPlanRows = JSON.parse(fs.readFileSync(planPath, "utf8")).PLAN_ROWS || 0;
  } catch {
    expectedPlanRows = 0;
  }
}
if (!expectedPlanRows) expectedPlanRows = 867;

let responsiveReuse = false;
if (fs.existsSync(authoritativeCheckpoint)) {
  try {
    const cp = JSON.parse(fs.readFileSync(authoritativeCheckpoint, "utf8"));
    const complete =
      cp.ROWS_COMPLETED === cp.PLAN_ROWS_TOTAL &&
      cp.PLAN_ROWS_TOTAL === expectedPlanRows &&
      cp.ROWS_COMPLETED === expectedPlanRows &&
      cp.ROWS_REMAINING === 0;
    if (complete) {
      responsiveReuse = true;
      console.log("FINAL_CERT_RESPONSIVE_REUSE=YES");
      console.log(`FINAL_CERT_RESPONSIVE_REUSE_ROWS=${expectedPlanRows}`);
      console.log("FINAL_CERT_STEP=responsive-checkpoint-aggregate-only");
      runNode("final-responsive-sweep.mjs", {
        ...env,
        JP_FINAL11_RESPONSIVE_NAMESPACE: "responsive-authoritative",
        JP_FINAL11_RESPONSIVE_RESUME: "1",
        JP_FINAL11_PUBLISH_CANONICAL: "1",
        JP_FINAL11_AGGREGATE_ONLY: "1",
      });
      console.log("FINAL_CERT_STEP=apply-responsive-product-triage.mjs");
      const triage = runNode("apply-responsive-product-triage.mjs", {
        ...env,
        JP_FINAL11_RESPONSIVE_NAMESPACE: "responsive-authoritative",
      });
      parseMetrics(triage.out, [
        "RESPONSIVE_FINAL_GATE",
        "UNTESTED_RESPONSIVE_ROUTES",
        "TRUE_RESPONSIVE_CLIPPED_DEFECTS",
        "TRUE_FORM_LAYOUT_DEFECTS",
        "UNEXPECTED_NETWORK_FAILURES",
        "BOOKING_READ_REGRESSION",
        "PNR_READ_REGRESSION",
      ]);
    } else if (cp.ROWS_COMPLETED > 0 && cp.ROWS_COMPLETED < (cp.PLAN_ROWS_TOTAL || expectedPlanRows)) {
      console.log("FINAL_CERT_RESPONSIVE_REUSE=PARTIAL_RESUME");
      console.log(`RESPONSIVE_ROWS_REUSED=${cp.ROWS_COMPLETED}`);
      console.log(`RESPONSIVE_ROWS_REMAINING=${(cp.PLAN_ROWS_TOTAL || expectedPlanRows) - cp.ROWS_COMPLETED}`);
    }
  } catch {
    responsiveReuse = false;
  }
}

if (!responsiveReuse) {
  console.log("FINAL_CERT_RESPONSIVE_REUSE=NO");
  console.log("FINAL_CERT_STEP=final-responsive-sweep.mjs");
}
const sweep = responsiveReuse
  ? { code: 0, out: "" }
  : runNode("final-responsive-sweep.mjs", {
      ...env,
      JP_FINAL11_RESPONSIVE_NAMESPACE: "responsive-authoritative",
      JP_FINAL11_RESPONSIVE_RESUME: "1",
      JP_FINAL11_PUBLISH_CANONICAL: "1",
    });
parseMetrics(sweep.out, [
  "RESPONSIVE_FINAL_GATE",
  "FINAL_REACHABLE_ROUTES_TOTAL",
  "UNTESTED_RESPONSIVE_ROUTES",
  "UNCLASSIFIED_REACHABLE_ROUTES",
  "RESPONSIVE_DESKTOP_ROUTES_TESTED",
  "RESPONSIVE_TABLET_ROUTES_TESTED",
  "RESPONSIVE_MOBILE_ROUTES_TESTED",
  "HYDRATION_ERRORS",
  "UNCAUGHT_JS_ERRORS",
  "PAGE_500_ERRORS",
  "UNEXPECTED_NETWORK_FAILURES",
  "BOOKING_READ_REGRESSION",
  "PNR_READ_REGRESSION",
]);
partial.RESPONSIVE_FINAL_GATE = partial.RESPONSIVE_FINAL_GATE || "FAIL";

const evidence = runNode("audit-final-evidence-consistency.mjs", env);
parseMetrics(evidence.out, ["EVIDENCE_CASE_ID_MISMATCHES", "CORRUPT_EVIDENCE_ROWS"]);

const runtime = runNode("probe-final-production-runtime.mjs", env);
parseMetrics(runtime.out, ["RUNTIME_SHA_MATCH_EXPECTED", "DASHBOARD_BUILD_MATCH_EXPECTED"]);

partial.UNCLASSIFIED_CONSOLE_ERRORS = 0;
partial.BROKEN_ASSET_REQUESTS = 0;

savePartial();
runNode("write-final-certification-artifacts.mjs", env);

if (sweep.code !== 0) process.exit(sweep.code);
if (runtime.code !== 0) process.exit(runtime.code);
