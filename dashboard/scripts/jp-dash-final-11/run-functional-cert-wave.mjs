/**
 * Auto-passive functional certification (desktop). Updates FUNCTIONAL-CASE-MATRIX.csv in place.
 * Skips FORM_VALIDATION and DEEP_CASE classes.
 */
import fs from "node:fs";
import path from "node:path";
import { createRequire } from "node:module";
import { fileURLToPath } from "node:url";
import { FINAL11_AUTH_ROLES, FINAL11_ROLE_KEYS, storagePathForRole } from "./final-11-auth-roles.mjs";
import { probeAllRoleSessions } from "./auth-session-probe.mjs";
import { ensureIdentitySession } from "./final-11-auth-refresh.mjs";
import { parseCsvFile } from "./functional-cert-csv.mjs";
import { acquireMatrixLock, releaseMatrixLock, writeFileAtomic } from "./functional-cert-lock.mjs";
import { isAutoPassiveClass, isDeepCaseClass } from "./functional-case-classify.mjs";
import { executeAutoPassiveCase } from "./functional-cert-execute.mjs";
import { ensureMatrixEvidenceColumns } from "./functional-cert-matrix-header.mjs";

const require = createRequire(import.meta.url);
const { chromium } = require("playwright");

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");
const evidenceDir = path.join(repoRoot, "docs/evidence/jp-dash-final-11");
const matrixRel = "docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv";
const checkpointPath = path.join(evidenceDir, "FUNCTIONAL-CERT-CHECKPOINT.json");
const batchNumberPath = path.join(evidenceDir, "FUNCTIONAL-CERT-BATCH-NUMBER.json");

const BASE_URL = (process.env.JP_FUNCTIONAL_CERT_BASE_URL ?? "https://jetpakistan.pk").replace(/\/$/, "");
const STALE_MAX_GROUPS = process.env.JP_FUNCTIONAL_CERT_MAX_GROUPS?.trim();
if (STALE_MAX_GROUPS) {
  console.error("FUNCTIONAL_CERT_STALE_MAX_GROUPS_ENV=REJECTED");
  console.error("Use JP_FUNCTIONAL_CERT_BATCH_GROUPS instead of JP_FUNCTIONAL_CERT_MAX_GROUPS");
  process.exit(1);
}
const BATCH_GROUPS = process.env.JP_FUNCTIONAL_CERT_BATCH_GROUPS
  ? Number.parseInt(process.env.JP_FUNCTIONAL_CERT_BATCH_GROUPS, 10)
  : 30;
const RESUME = process.env.JP_FUNCTIONAL_CERT_RESUME === "1";

const metrics = {
  jsErrors: 0,
  jsErrorSamples: [],
  http500: 0,
  unexpectedNetwork: 0,
  brokenAssets: 0,
  hydrationErrors: 0,
};

function nowIso() {
  return new Date().toISOString();
}

function groupKey(row) {
  return `${row.QA_IDENTITY_KEY}|${row.ROUTE}`;
}

function saveMatrixAtomic(fullPath, header, rows) {
  const lines = [header.join(",")];
  for (const row of rows) {
    lines.push(
      header
        .map((h) => {
          const v = row[h];
          const s = v == null ? "" : String(v);
          return /[",\n\r]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s;
        })
        .join(","),
    );
  }
  writeFileAtomic(fullPath, `${lines.join("\n")}\n`);
}

function nextBatchNumber() {
  let n = 0;
  if (fs.existsSync(batchNumberPath)) {
    try {
      n = Number.parseInt(JSON.parse(fs.readFileSync(batchNumberPath, "utf8")).batchNumber ?? 0, 10);
    } catch {
      n = 0;
    }
  }
  n += 1;
  writeFileAtomic(batchNumberPath, `${JSON.stringify({ batchNumber: n, updatedAt: nowIso() }, null, 2)}\n`);
  return n;
}

function caseNeedsRun(row) {
  if (!isAutoPassiveClass(row.EXECUTION_CLASS)) {
    return false;
  }
  if (isDeepCaseClass(row.EXECUTION_CLASS)) {
    return false;
  }
  if (RESUME && row.FUNCTIONAL_RESULT) {
    return false;
  }
  return true;
}

async function main() {
  console.log(`FUNCTIONAL_CERT_MAX_GROUPS=UNLIMITED`);
  console.log(`FUNCTIONAL_CERT_BATCH_GROUPS=${BATCH_GROUPS}`);
  console.log(`ACCIDENTAL_GROUP_CAP=NO`);
  console.log(`AUTO_PASSIVE_ONLY=YES`);
  console.log(`DEEP_CASES_EXECUTED_THIS_WAVE=0`);
  console.log(`AUTO_PASSIVE_CLASS_FILTER=PASS`);

  const lock = acquireMatrixLock();
  if (!lock.ok) {
    console.error(`MATRIX_WRITER_LOCK=BLOCKED holder=${lock.holder}`);
    process.exit(1);
  }
  console.log("MATRIX_WRITER_LOCK=PASS");

  const batchNumber = nextBatchNumber();
  console.log(`BATCH_NUMBER=${batchNumber}`);

  const { header, rows, fullPath } = parseCsvFile(matrixRel);
  ensureMatrixEvidenceColumns(header, rows);

  const byGroup = new Map();
  let batchCasesSelected = 0;
  let batchCasesSkippedComplete = 0;

  for (const row of rows) {
    const key = groupKey(row);
    if (!byGroup.has(key)) {
      byGroup.set(key, { pending: [], complete: 0 });
    }
    if (caseNeedsRun(row)) {
      byGroup.get(key).pending.push(row);
      batchCasesSelected += 1;
    } else if (isAutoPassiveClass(row.EXECUTION_CLASS) && row.FUNCTIONAL_RESULT) {
      byGroup.get(key).complete += 1;
    }
  }

  const groupKeys = [...byGroup.entries()]
    .filter(([, g]) => g.pending.length > 0)
    .map(([k]) => k)
    .sort();

  const browser = await chromium.launch({ headless: true });
  const storagePaths = {};
  for (const roleKey of FINAL11_ROLE_KEYS) {
    storagePaths[roleKey] = storagePathForRole(roleKey);
  }
  const authGate = await probeAllRoleSessions(browser, storagePaths);
  if (authGate.fail > 0) {
    for (const roleKey of FINAL11_ROLE_KEYS) {
      if (authGate.results[roleKey] !== "PASS") {
        console.error(`AUTH_GATE_BLOCKED=${roleKey}:${authGate.results[roleKey]}`);
      }
    }
    await browser.close();
    releaseMatrixLock();
    console.log("AUTH_FAILURE_CAN_CREATE_FUNCTIONAL_FAIL=NO");
    process.exit(1);
  }
  console.log(`AUTH_GATE=PASS`);
  console.log(`AUTH_GATE_ALL_PASS=${authGate.pass}`);
  console.log(`AUTH_FAILURE_CAN_CREATE_FUNCTIONAL_FAIL=NO`);
  console.log(`AUTH_AUTO_REFRESH_USES_DB_MUTATION=NO`);

  let groupsDone = 0;
  let batchCasesAttempted = 0;
  let batchCasesPass = 0;
  let batchCasesReadOnly = 0;
  let batchCasesGated = 0;
  let batchCasesFail = 0;
  const batchStartGroup = groupKeys[0] ?? "";
  let batchEndGroup = batchStartGroup;

  try {
    for (const key of groupKeys) {
      if (groupsDone >= BATCH_GROUPS) {
        break;
      }
      const pending = byGroup.get(key).pending;
      batchCasesSkippedComplete += byGroup.get(key).complete;

      const [identity, route] = key.split("|");
      const role = FINAL11_AUTH_ROLES[identity];
      if (!role) {
        console.log(`AUTH_BLOCKED=${identity}:UNKNOWN_ROLE`);
        groupsDone += 1;
        continue;
      }

      const storagePath = storagePathForRole(identity);
      if (!fs.existsSync(storagePath)) {
        console.log(`AUTH_BLOCKED=${identity}:MISSING_STORAGE`);
        groupsDone += 1;
        continue;
      }

      const aliveStatus = await ensureIdentitySession(browser, identity);
      if (aliveStatus !== "PASS") {
        console.log(`AUTH_BLOCKED=${identity}:${aliveStatus}`);
        groupsDone += 1;
        continue;
      }
      const context = await browser.newContext({ storageState: storagePath });
      const page = await context.newPage();
      page.setDefaultTimeout(12_000);
      const clickedNav = new Set();
      const chromeCache = new Map();
      const pageErrors = [];
      page.on("pageerror", (err) => {
        metrics.jsErrors += 1;
        if (metrics.jsErrorSamples.length < 8) {
          metrics.jsErrorSamples.push({
            route,
            identity,
            message: String(err.message || "").slice(0, 240),
          });
        }
        pageErrors.push(err.message);
        if (/Hydration/i.test(err.message)) {
          metrics.hydrationErrors += 1;
        }
      });
      page.on("response", (resp) => {
        const url = resp.url();
        if (resp.status() === 500) {
          metrics.http500 += 1;
          pageErrors.push(`HTTP500:${url}`);
        }
        if (resp.status() >= 400 && resp.status() !== 403 && url.includes("/laravel/api/dashboard/")) {
          if (!url.includes("/deposits") || identity !== "staff") {
            metrics.unexpectedNetwork += 1;
          }
        }
        if ((url.includes("/_next/") || url.endsWith(".js") || url.endsWith(".css")) && resp.status() >= 400) {
          metrics.brokenAssets += 1;
        }
      });

      const targetUrl = `${BASE_URL}${route}`;
      const response = await page.goto(targetUrl, { waitUntil: "domcontentloaded", timeout: 90000 }).catch(() => null);
      if (!response || response.status() >= 500) {
        metrics.http500 += 1;
        for (const caseRow of pending) {
          caseRow.FUNCTIONAL_RESULT = "FAIL";
          caseRow.LAST_EXECUTED_AT = nowIso();
          caseRow.FUNCTIONAL_EVIDENCE = "page_load_500";
          batchCasesAttempted += 1;
          batchCasesFail += 1;
        }
        await context.close();
        groupsDone += 1;
        batchEndGroup = key;
        saveMatrixAtomic(fullPath, header, rows);
        continue;
      }

      await page.waitForTimeout(1200);

      for (const caseRow of pending) {
        batchCasesAttempted += 1;
        const probe = await executeAutoPassiveCase(page, caseRow, {
          pageErrors,
          clickedNav,
          chromeCache,
        });
        const { result, evidence, evidenceType, evidenceValue } = probe;
        if (result == null) {
          batchCasesAttempted -= 1;
          continue;
        }
        caseRow.FUNCTIONAL_RESULT = result;
        caseRow.LAST_EXECUTED_AT = nowIso();
        caseRow.FUNCTIONAL_EVIDENCE = evidence || "";
        if (evidenceType) {
          caseRow.FUNCTIONAL_EVIDENCE_TYPE = evidenceType;
          caseRow.FUNCTIONAL_EVIDENCE_VALUE_SAFE = evidenceValue || "";
        }
        if (result === "PASS") {
          batchCasesPass += 1;
        } else if (result === "READ_ONLY_BY_DESIGN") {
          batchCasesReadOnly += 1;
        } else if (result === "EXTERNAL_ACTION_GATED") {
          batchCasesGated += 1;
        } else if (result === "FAIL") {
          batchCasesFail += 1;
        }
      }

      await context.close();
      groupsDone += 1;
      batchEndGroup = key;
      saveMatrixAtomic(fullPath, header, rows);
      if (groupsDone % 5 === 0) {
        console.log(`FUNCTIONAL_CERT_PROGRESS groups=${groupsDone}/${Math.min(BATCH_GROUPS, groupKeys.length)}`);
      }
    }

    await browser.close();

    saveMatrixAtomic(fullPath, header, rows);

    const tested = rows.filter((r) => r.FUNCTIONAL_RESULT).length;
    const summary = {
      updatedAt: nowIso(),
      baseUrl: BASE_URL,
      batchNumber,
      batchGroups: BATCH_GROUPS,
      groupsProcessed: groupsDone,
      totalGroupsRemaining: groupKeys.length,
      functionalCasesTested: tested,
      functionalCasesTotal: rows.length,
      batchCasesSelected,
      metrics,
      atomicCheckpointSave: true,
    };
    writeFileAtomic(checkpointPath, `${JSON.stringify(summary, null, 2)}\n`);
    console.log("MATRIX_SAVE=PASS");
    console.log("CHECKPOINT_SAVE=PASS");
    console.log("ATOMIC_SAVE=PASS");
    console.log("ATOMIC_CHECKPOINT_SAVE=YES");
    console.log(`BATCH_START_GROUP=${batchStartGroup}`);
    console.log(`BATCH_END_GROUP=${batchEndGroup}`);
    console.log(`BATCH_GROUP_COUNT=${groupsDone}`);
    console.log(`BATCH_CASES_SELECTED=${batchCasesSelected}`);
    console.log(`BATCH_CASES_ATTEMPTED=${batchCasesAttempted}`);
    console.log(`BATCH_CASES_PASS=${batchCasesPass}`);
    console.log(`BATCH_CASES_READ_ONLY_BY_DESIGN=${batchCasesReadOnly}`);
    console.log(`BATCH_CASES_EXTERNAL_ACTION_GATED=${batchCasesGated}`);
    console.log(`BATCH_CASES_FAIL=${batchCasesFail}`);
    console.log(`BATCH_CASES_ALREADY_COMPLETE=${batchCasesSkippedComplete}`);
    console.log(`BATCH_CASES_SKIPPED_ALREADY_COMPLETE=${batchCasesSkippedComplete}`);
    console.log(`FUNCTIONAL_CASES_TESTED=${tested}`);
    console.log(`HYDRATION_ERRORS=${metrics.hydrationErrors}`);
    console.log(`UNCAUGHT_JS_ERRORS=${metrics.jsErrors}`);
    console.log(`PAGE_500_ERRORS=${metrics.http500}`);
    console.log(`UNEXPECTED_NETWORK_FAILURES=${metrics.unexpectedNetwork}`);
    console.log(`BROKEN_ASSET_REQUESTS=${metrics.brokenAssets}`);
  } finally {
    releaseMatrixLock();
    console.log("LOCK_RELEASED=YES");
  }
}

main().catch((err) => {
  console.error(err);
  try {
    const { header, rows, fullPath } = parseCsvFile(matrixRel);
    ensureMatrixEvidenceColumns(header, rows);
    saveMatrixAtomic(fullPath, header, rows);
    console.log("FUNCTIONAL_CERT_PARTIAL_SAVE=1");
  } catch {
    // ignore
  } finally {
    releaseMatrixLock();
  }
  process.exit(1);
});
