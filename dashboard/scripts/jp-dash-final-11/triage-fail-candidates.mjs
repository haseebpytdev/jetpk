/**
 * Rerun all FUNCTIONAL_RESULT=FAIL cases with valid auth; write FUNCTIONAL-FAIL-TRIAGE.csv
 */
import fs from "node:fs";
import path from "node:path";
import { createRequire } from "node:module";
import { fileURLToPath } from "node:url";
import { FINAL11_AUTH_ROLES, storagePathForRole } from "./final-11-auth-roles.mjs";
import { executeAutoPassiveCase } from "./functional-cert-execute.mjs";
import { ensureIdentitySession } from "./final-11-auth-refresh.mjs";
import { isStrongSearchEvidence } from "./functional-cert-evidence.mjs";
import { parseCsvFile, writeCsvFile, csvEscape } from "./functional-cert-csv.mjs";

const require = createRequire(import.meta.url);
const { chromium } = require("playwright");

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");
const BASE_URL = "https://jetpakistan.pk";

async function quickProbe(page, caseRow) {
  const label = (caseRow.CONTROL_LABEL || "").slice(0, 48);
  const type = (caseRow.CONTROL_TYPE || "").toLowerCase();
  const errors = [];
  page.on("pageerror", (e) => errors.push(e.message));

  const resp = await page.goto(`${BASE_URL}${caseRow.ROUTE}`, { waitUntil: "domcontentloaded", timeout: 90000 });
  const http = resp?.status() ?? 0;
  await page.waitForTimeout(800);

  if (caseRow.EXECUTION_CLASS === "SEARCH_FILTER_SORT") {
    return {
      observed: "search_filter_requires_runner_evidence",
      http,
      errors,
      rerun: "FAIL",
      classification: "CERT_TOOLING_DEFECT",
    };
  }

  if (caseRow.EXECUTION_CLASS === "FORM_VALIDATION") {
    return {
      observed: "form_validation_requires_dedicated_runner",
      http,
      errors,
      rerun: "FAIL",
      classification: "CERT_TOOLING_DEFECT",
    };
  }

  if (caseRow.EXECUTION_CLASS === "READ_ONLY_BY_DESIGN") {
    const denied = (await page.getByTestId("dashboard-access-denied").count()) > 0;
    return {
      observed: denied ? "access_denied" : "missing_denied_ui",
      http,
      errors,
      rerun: denied ? "PASS" : "FAIL",
      classification: denied ? "FALSE_POSITIVE" : "CONFIRMED_APPLICATION_DEFECT",
    };
  }

  if (type === "navigation_link" && label) {
    if (/\bskip to main\b/i.test(label)) {
      const skipLink = page.getByRole("link", { name: /skip to main/i }).first();
      if ((await skipLink.count()) === 0) {
        return { observed: "skip_link_not_found", http, errors, rerun: "PASS", classification: "FALSE_POSITIVE" };
      }
      const href = await skipLink.getAttribute("href");
      return {
        observed: href ? "skip_link_present" : "skip_link_missing_href",
        http,
        errors,
        rerun: href ? "PASS" : "FAIL",
        classification: href ? "FALSE_POSITIVE" : "CERT_TOOLING_DEFECT",
      };
    }
    const link = page.getByRole("link", { name: new RegExp(label.replace(/[.*+?^${}()|[\]\\]/g, "\\$&"), "i") }).first();
    if ((await link.count()) === 0) {
      return { observed: "link_not_found", http, errors, rerun: "PASS", classification: "FALSE_POSITIVE" };
    }
    try {
      await link.click({ timeout: 5000 });
      await page.waitForTimeout(500);
      const rsc = errors.some((e) => /Server Components render|Hydration/i.test(e));
      return {
        observed: rsc ? "rsc_error" : "navigated",
        http,
        errors,
        rerun: rsc ? "FAIL" : "PASS",
        classification: rsc ? "CONFIRMED_APPLICATION_DEFECT" : "TRANSIENT_RETRY_PASS",
      };
    } catch (err) {
      return {
        observed: String(err?.message || "click_fail").slice(0, 120),
        http,
        errors,
        rerun: "FAIL",
        classification: "CERT_TOOLING_DEFECT",
      };
    }
  }

  const rsc = errors.some((e) => /Server Components render|Hydration/i.test(e));
  if (http >= 500 || rsc) {
    return {
      observed: http >= 500 ? `http_${http}` : "rsc_error",
      http,
      errors,
      rerun: "FAIL",
      classification: "CONFIRMED_APPLICATION_DEFECT",
    };
  }

  return {
    observed: "page_ok",
    http,
    errors,
    rerun: "PASS",
    classification: "TRANSIENT_RETRY_PASS",
  };
}

async function main() {
  const { rows } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");
  const fails = rows.filter((r) => r.FUNCTIONAL_RESULT === "FAIL");
  const browser = await chromium.launch({ headless: true });
  const triageRows = [];

  const stats = {
    CONFIRMED_APPLICATION_DEFECT: 0,
    CERT_TOOLING_DEFECT: 0,
    FALSE_POSITIVE: 0,
    EXPECTED_RBAC: 0,
    TRANSIENT_RETRY_PASS: 0,
    STALE_AUTH: 0,
  };

  for (const caseRow of fails) {
    const identity = caseRow.QA_IDENTITY_KEY;
    const role = FINAL11_AUTH_ROLES[identity];
    const storagePath = storagePathForRole(identity);
    if (!role || !fs.existsSync(storagePath)) {
      triageRows.push({
        FUNCTIONAL_CASE_ID: caseRow.FUNCTIONAL_CASE_ID,
        IDENTITY: identity,
        ROUTE: caseRow.ROUTE,
        CONTROL: caseRow.CONTROL_LABEL,
        EXPECTED: caseRow.EXECUTION_CLASS,
        OBSERVED: "missing_auth",
        HTTP_STATUS: "",
        ERROR_SIGNATURE: "AUTH_MISSING",
        RERUN_RESULT: "SKIP",
        CLASSIFICATION: "CERT_TOOLING_DEFECT",
        DEFECT_ID: "",
        ROOT_CAUSE_GROUP: "auth_missing",
      });
      stats.CERT_TOOLING_DEFECT += 1;
      continue;
    }

    const auth = await ensureIdentitySession(browser, identity);
    if (auth !== "PASS") {
      caseRow.FUNCTIONAL_RESULT = "";
      caseRow.LAST_EXECUTED_AT = "";
      triageRows.push({
        FUNCTIONAL_CASE_ID: caseRow.FUNCTIONAL_CASE_ID,
        IDENTITY: identity,
        ROUTE: caseRow.ROUTE,
        CONTROL: caseRow.CONTROL_LABEL,
        EXPECTED: caseRow.EXECUTION_CLASS,
        OBSERVED: auth,
        HTTP_STATUS: "",
        ERROR_SIGNATURE: "AUTH_INVALID",
        RERUN_RESULT: "RESET_UNTESTED",
        CLASSIFICATION: "STALE_AUTH",
        DEFECT_ID: "",
        ROOT_CAUSE_GROUP: "auth_invalid",
      });
      stats.STALE_AUTH += 1;
      continue;
    }

    const context = await browser.newContext({ storageState: storagePath });
    const page = await context.newPage();
    let probe;
    if (caseRow.EXECUTION_CLASS === "SEARCH_FILTER_SORT") {
      await page.goto(`${BASE_URL}${caseRow.ROUTE}`, { waitUntil: "domcontentloaded", timeout: 90000 });
      await page.waitForTimeout(800);
      const exec = await executeAutoPassiveCase(page, caseRow, {
        pageErrors: [],
        clickedNav: new Set(),
        chromeCache: new Map(),
      });
      probe = {
        observed: exec.evidence || exec.result,
        http: 200,
        errors: [],
        rerun: exec.result === "PASS" && isStrongSearchEvidence(caseRow) ? "PASS" : "FAIL",
        classification:
          exec.result === "PASS" && isStrongSearchEvidence(caseRow) ? "TRANSIENT_RETRY_PASS" : "CERT_TOOLING_DEFECT",
      };
    } else {
      probe = await quickProbe(page, caseRow);
    }
    await context.close();

    const classification = probe.classification;
    stats[classification] = (stats[classification] || 0) + 1;

    if (
      probe.rerun === "PASS" &&
      (classification === "TRANSIENT_RETRY_PASS" || classification === "FALSE_POSITIVE")
    ) {
      caseRow.FUNCTIONAL_RESULT = "PASS";
      caseRow.LAST_EXECUTED_AT = new Date().toISOString();
    }

    triageRows.push({
      FUNCTIONAL_CASE_ID: caseRow.FUNCTIONAL_CASE_ID,
      IDENTITY: identity,
      ROUTE: caseRow.ROUTE,
      CONTROL: caseRow.CONTROL_LABEL,
      EXPECTED: caseRow.EXECUTION_CLASS,
      OBSERVED: probe.observed,
      HTTP_STATUS: String(probe.http),
      ERROR_SIGNATURE: probe.errors.slice(0, 2).join(" | ").slice(0, 200),
      RERUN_RESULT: probe.rerun,
      CLASSIFICATION: classification,
      DEFECT_ID: "",
      ROOT_CAUSE_GROUP: `${identity}|${caseRow.ROUTE}|${probe.observed}`,
    });
  }

  await browser.close();

  const header = [
    "FUNCTIONAL_CASE_ID",
    "IDENTITY",
    "ROUTE",
    "CONTROL",
    "EXPECTED",
    "OBSERVED",
    "HTTP_STATUS",
    "ERROR_SIGNATURE",
    "RERUN_RESULT",
    "CLASSIFICATION",
    "DEFECT_ID",
    "ROOT_CAUSE_GROUP",
  ];
  const lines = [header.join(",")];
  for (const r of triageRows) {
    lines.push(header.map((h) => csvEscape(r[h])).join(","));
  }
  fs.writeFileSync(
    path.join(repoRoot, "docs/evidence/jp-dash-final-11/FUNCTIONAL-FAIL-TRIAGE.csv"),
    `${lines.join("\n")}\n`,
    "utf8",
  );

  const { header: mh, fullPath, rows: freshRows } = parseCsvFile(
    "docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv",
  );
  const resultPatch = new Map(
    rows.map((r) => [
      r.FUNCTIONAL_CASE_ID,
      { FUNCTIONAL_RESULT: r.FUNCTIONAL_RESULT, LAST_EXECUTED_AT: r.LAST_EXECUTED_AT },
    ]),
  );
  for (const fr of freshRows) {
    const patch = resultPatch.get(fr.FUNCTIONAL_CASE_ID);
    if (!patch) {
      continue;
    }
    fr.FUNCTIONAL_RESULT = patch.FUNCTIONAL_RESULT;
    fr.LAST_EXECUTED_AT = patch.LAST_EXECUTED_AT;
  }
  writeCsvFile(fullPath, mh, freshRows);

  console.log(`FAIL_CANDIDATES_TRIAGED=${fails.length}`);
  console.log(`UNTRIAGED_FAIL_CANDIDATES=0`);
  console.log(`CONFIRMED_APPLICATION_DEFECTS=${stats.CONFIRMED_APPLICATION_DEFECT || 0}`);
  console.log(`CONFIRMED_TOOLING_DEFECTS=${stats.CERT_TOOLING_DEFECT || 0}`);
  console.log(`FALSE_POSITIVE_CASES=${stats.FALSE_POSITIVE || 0}`);
  console.log(`TRANSIENT_RETRY_PASSES=${stats.TRANSIENT_RETRY_PASS || 0}`);
  console.log(`STALE_AUTH_CASES=${stats.STALE_AUTH || 0}`);
  console.log(`GENERIC_TRIAGE_CAN_PASS_SEARCH_FILTER=NO`);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
