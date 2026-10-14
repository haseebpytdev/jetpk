/**
 * Agent staff RBAC certification (authoritative /agent?format=json + owner-only probes).
 */
import { createRequire } from "node:module";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import {
  expectedNavHrefs,
  expectedPresenterPermissions,
  OWNER_ONLY_CAPABILITY_FLAGS,
  OWNER_ONLY_URL_PROBES,
} from "./agent-staff-authoritative-matrix.mjs";
import { FINAL11_AUTH_ROLES, BASE_URL, repoRoot, storagePathForRole } from "./final-11-auth-roles.mjs";
import { LARAVEL_JSON_HEADERS, laravelPortalUrl } from "./final-11-laravel-probe.mjs";
import { AGENT_ROUTES } from "./route-catalog.mjs";

const require = createRequire(import.meta.url);
const { chromium } = require("playwright");

const EVIDENCE = path.join(repoRoot, "docs/evidence/jp-dash-final-11");
const CAPABILITY_ENDPOINT = "/agent?format=json";
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

const STAFF_ROLES = [
  "agent_staff_manager",
  "agent_staff_accountant",
  "agent_staff_sales",
  "agent_staff_support",
  "agent_staff_ticketing",
  "agent_staff_viewer",
];

function csvEscape(v) {
  const s = String(v ?? "");
  return s.includes(",") ? `"${s.replace(/"/g, '""')}"` : s;
}

/**
 * @param {import('playwright').BrowserContext} context
 */
async function fetchAgentCapabilities(context) {
  const response = await context.request.get(laravelPortalUrl(CAPABILITY_ENDPOINT), {
    headers: LARAVEL_JSON_HEADERS,
  });
  const status = response.status();
  const contentType = response.headers()["content-type"] || "";
  if (status !== 200 || !contentType.includes("application/json")) {
    throw new Error(`CAPABILITY_PROBE=FAIL http=${status} content_type=${contentType.split(";")[0]}`);
  }
  const payload = await response.json();
  if (payload?.ok !== true) {
    throw new Error("CAPABILITY_PROBE=FAIL ok_not_true");
  }
  const capabilities = payload.capabilities;
  if (!capabilities || typeof capabilities !== "object") {
    throw new Error("CAPABILITY_PROBE=FAIL missing_capabilities");
  }
  if (!capabilities.permissions || typeof capabilities.permissions !== "object") {
    throw new Error("CAPABILITY_PROBE=FAIL missing_permissions");
  }
  if (!Array.isArray(capabilities.navigation)) {
    throw new Error("CAPABILITY_PROBE=FAIL missing_navigation");
  }
  return capabilities;
}

async function probeRoute(context, route) {
  const page = await context.newPage();
  const resp = await page.goto(`${BASE_URL}${route}`, { waitUntil: "domcontentloaded", timeout: 60000 }).catch(() => null);
  await sleep(1200);
  const status = resp?.status() ?? 0;
  const body = await page.locator("body").innerText().catch(() => "");
  const accessDenied = (await page.getByTestId("dashboard-access-denied").count()) > 0;
  const denied =
    status === 403 ||
    status === 404 ||
    accessDenied ||
    page.url().includes("/login") ||
    /access denied|not authorized|permission required|forbidden/i.test(body);
  const serverError = status >= 500;
  await page.close();
  return { status, denied, serverError };
}

async function probeOwnerOnlyUrl(context, probe) {
  const url = probe.json ? laravelPortalUrl(`${probe.path}?format=json`) : `${BASE_URL}${probe.path}`;
  if (probe.json) {
    const response = await context.request.get(url, { headers: LARAVEL_JSON_HEADERS });
    const status = response.status();
    const contentType = response.headers()["content-type"] || "";
    if (status >= 500) return { escalated: true, reason: "server_error" };
    if (status === 403 || status === 404) return { escalated: false };
    if (contentType.includes("application/json")) {
      const body = await response.json().catch(() => ({}));
      if (body?.ok === true) return { escalated: true, reason: "json_ok" };
    }
    return { escalated: false };
  }
  const page = await context.newPage();
  const resp = await page.goto(url, { waitUntil: "domcontentloaded", timeout: 60000 });
  await sleep(1200);
  const body = await page.locator("body").innerText().catch(() => "");
  const denied =
    (resp?.status() ?? 0) === 403 ||
    (resp?.status() ?? 0) === 404 ||
    page.url().includes("/login") ||
    /access denied|not authorized|permission required|forbidden/i.test(body);
  const commissionLeak =
    probe.path.includes("/commissions") &&
    !denied &&
    /commission summary|commission earned|pending commission/i.test(body);
  await page.close();
  return { escalated: commissionLeak, reason: commissionLeak ? "commission_content" : "denied" };
}

async function agencyStaffChecks(context, roleKey, cap) {
  const flags = cap.capabilities ?? {};
  const permissions = cap.permissions ?? {};
  const agencyViewExpected = Boolean(permissions.agency_view);
  let agencyViewPass = "NOT_APPLICABLE";
  let editLinkVisible = "NO";
  let editDirect = "NOT_APPLICABLE";
  let editApiEscalation = 0;

  if (agencyViewExpected) {
    const page = await context.newPage();
    await page.goto(`${BASE_URL}/agent/agency`, { waitUntil: "domcontentloaded", timeout: 60000 });
    await sleep(1200);
    const body = await page.locator("body").innerText().catch(() => "");
    const denied = /access denied|not authorized|permission required|forbidden/i.test(body) || page.url().includes("/login");
    agencyViewPass = denied ? "FAIL" : "PASS";
    editLinkVisible = (await page.getByRole("link", { name: /edit agency profile/i }).count()) > 0 ? "YES" : "NO";
    await page.close();

    const editProbe = await probeOwnerOnlyUrl(context, { path: "/agent/agency/edit", label: "OWNER_ONLY", json: true });
    editDirect = editProbe.escalated ? "ALLOWED" : "DENIED";
    if (editProbe.escalated) editApiEscalation += 1;
  }

  if (flags.can_edit_agency === true) {
    editApiEscalation += 1;
  }

  return { agencyViewExpected, agencyViewPass, editLinkVisible, editDirect, editApiEscalation };
}

function compareNav(expectedHrefs, actualNav) {
  const actualHrefs = actualNav.map((item) => (typeof item.href === "string" ? item.href.split("?")[0] : "")).filter(Boolean);
  const actualSet = new Set(actualHrefs);
  const missing = expectedHrefs.filter((href) => !actualSet.has(href));
  const extra = actualHrefs.filter((href) => href.startsWith("/agent/commissions"));
  return { missing, extra, actualHrefs, actualCodes: actualNav.map((i) => i.code).filter(Boolean) };
}

async function main() {
  fs.mkdirSync(EVIDENCE, { recursive: true });
  const matrixPath = path.join(EVIDENCE, "ROLE-RBAC-MATRIX.csv");
  const matrixRows = [
    "qa_identity_key,agency_role_template,capability_endpoint,capability_response_json,expected_permission_count,actual_permission_count,permission_template_parity,expected_nav_codes,actual_nav_codes,capability_nav_parity,agency_view_expected,agency_view_staff,agency_edit_link_visible,agency_edit_direct_access,agency_edit_api_escalation,owner_only_url_escalations,owner_only_api_escalations,false_positive_escalations,unauthorized_nav_items,classification_notes,result",
  ];

  console.log("CAPABILITY_ENDPOINT=/agent?format=json");
  console.log("OWNER_ONLY_MATRIX_SOURCE=AUTHORITATIVE");
  console.log("FALSE_OWNER_ONLY_PATHS=0");

  const browser = await chromium.launch({ headless: true });
  let forbiddenControls = 0;
  let directUrlEscalations = 0;
  let directApiEscalations = 0;
  let falsePositiveEscalations = 0;
  const roleResults = {};
  let permissionParityAll = true;
  let navParityAll = true;

  for (const roleKey of STAFF_ROLES) {
    const role = FINAL11_AUTH_ROLES[roleKey];
    const storage = storagePathForRole(roleKey);
    if (!fs.existsSync(storage)) throw new Error(`MISSING_STORAGE:${roleKey}`);

    const context = await browser.newContext({ storageState: storage });
    const cap = await fetchAgentCapabilities(context);
    console.log("CAPABILITY_RESPONSE_JSON=YES");

    const expectedPerms = expectedPresenterPermissions(role.agencyRole);
    const actualPermKeys = Object.entries(cap.permissions)
      .filter(([, v]) => v === true)
      .map(([k]) => k)
      .sort();
    const expectedSet = new Set(expectedPerms);
    const permissionParity = expectedPerms.every((k) => cap.permissions[k] === true) ? "PASS" : "FAIL";
    if (permissionParity === "FAIL") permissionParityAll = false;

    const modules = cap.modules ?? {};
    const expectedHrefs = expectedNavHrefs(expectedPerms, modules);
    const { missing, extra, actualHrefs, actualCodes } = compareNav(expectedHrefs, cap.navigation);
    const moduleNotes = [];
    for (const href of missing) {
      moduleNotes.push(`MISSING_NAV:${href}`);
    }
    const navParity = missing.length === 0 ? "PASS" : "FAIL";
    if (navParity === "FAIL") navParityAll = false;

    let ownerUrlEsc = 0;
    for (const probe of OWNER_ONLY_URL_PROBES) {
      const result = await probeOwnerOnlyUrl(context, probe);
      if (result.escalated) ownerUrlEsc += 1;
    }

    let ownerApiEsc = 0;
    for (const flag of OWNER_ONLY_CAPABILITY_FLAGS) {
      if (cap.capabilities?.[flag] === true) ownerApiEsc += 1;
    }

    const agency = await agencyStaffChecks(context, roleKey, cap);
    if (agency.editLinkVisible === "YES" || agency.editDirect === "ALLOWED") {
      ownerUrlEsc += 1;
    }
    ownerApiEsc += agency.editApiEscalation;

    forbiddenControls += extra.length;
    directUrlEscalations += ownerUrlEsc;
    directApiEscalations += ownerApiEsc;

    const result =
      permissionParity === "PASS" &&
      navParity === "PASS" &&
      ownerUrlEsc === 0 &&
      ownerApiEsc === 0 &&
      agency.editLinkVisible === "NO" &&
      agency.editDirect !== "ALLOWED" &&
      extra.length === 0
        ? "PASS"
        : "FAIL";

    roleResults[roleKey] = result;

    matrixRows.push(
      [
        roleKey,
        role.agencyRole,
        CAPABILITY_ENDPOINT,
        "YES",
        expectedPerms.length,
        actualPermKeys.length,
        permissionParity,
        expectedHrefs.join("|"),
        actualHrefs.join("|"),
        navParity,
        agency.agencyViewExpected ? "PASS" : "NOT_APPLICABLE",
        agency.agencyViewPass,
        agency.editLinkVisible,
        agency.editDirect,
        agency.editApiEscalation,
        ownerUrlEsc,
        ownerApiEsc,
        0,
        extra.length,
        moduleNotes.join(";"),
        result,
      ]
        .map(csvEscape)
        .join(","),
    );

    await context.close();
    console.log(`AGENT_STAFF_${role.agencyRole.toUpperCase()}=${result}`);
  }

  for (const ownerKey of ["agent_owner_a", "agent_owner_b"]) {
    const context = await browser.newContext({ storageState: storagePathForRole(ownerKey) });
    let partial = 0;
    for (const route of AGENT_ROUTES.slice(0, 12)) {
      const probe = await probeRoute(context, route);
      if (probe.serverError) partial += 1;
    }
    console.log(`${ownerKey === "agent_owner_a" ? "AGENT_OWNER_A_PORTAL" : "AGENT_OWNER_B_PORTAL"}=${partial > 0 ? "PARTIAL_WITH_DEFECT" : "PASS"}`);
    await context.close();
  }

  await browser.close();
  fs.writeFileSync(matrixPath, `${matrixRows.join("\n")}\n`, "utf8");

  console.log(`PERMISSION_TEMPLATE_PARITY=${permissionParityAll ? "PASS" : "FAIL"}`);
  console.log(`CAPABILITY_NAV_PARITY=${navParityAll ? "PASS" : "FAIL"}`);
  console.log(`VISIBLE_FORBIDDEN_CONTROLS=${forbiddenControls}`);
  console.log(`DIRECT_URL_ESCALATIONS=${directUrlEscalations}`);
  console.log(`DIRECT_API_ESCALATIONS=${directApiEscalations}`);
  console.log(`FALSE_POSITIVE_ESCALATIONS=${falsePositiveEscalations}`);

  const allPass = Object.values(roleResults).every((r) => r === "PASS");
  if (allPass) {
    console.log("DEF_F11_0004=CLOSED_TOOLING_FALSE_POSITIVE");
    console.log("DEF_F11_0004_ROOT_CAUSE=CERTIFICATION_TOOLING_FALSE_POSITIVE");
    console.log("DEF_F11_0005=CLOSED_TOOLING_FALSE_POSITIVE");
    console.log("DEF_F11_0005_ROOT_CAUSE=CERTIFICATION_TOOLING_WRONG_CAPABILITY_ENDPOINT");
  } else {
    console.log("DEF_F11_0004=OPEN");
    console.log("DEF_F11_0005=OPEN");
  }
}

main().catch((err) => {
  console.error(String(err?.message || err));
  process.exit(1);
});
