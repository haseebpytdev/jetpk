/**
 * Authenticated production Admin crawler for JP-DASH-03 acceptance.
 * Requires local storageState from admin login bootstrap.
 *
 * Usage: node scripts/jp-dash-03-production-crawl.mjs
 */
import { chromium } from "@playwright/test";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import {
  LARAVEL_ADMIN_HANDOFFS,
  NEXT_ADMIN_PAGES,
  PREVIEW_RESIDUE_PATTERNS,
  PRIVATE_ORIGIN_PATTERNS,
  scanTextForHits,
} from "./jp-dash-03-acceptance/forbidden-patterns.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../");
const storagePath = path.join(repoRoot, "tmp/jp-dash-03-admin-storage-state.json");
const matrixPath = path.join(repoRoot, "docs/jetpk/JP-DASH-03-PAGE-MATRIX.json");
const navMatrixPath = path.join(repoRoot, "docs/jetpk/JP-DASH-03-NAV-MATRIX.json");

const baseUrl = process.env.JP_ACCEPTANCE_BASE_URL ?? "https://jetpakistan.pk";

function sanitizeUrl(url) {
  try {
    const parsed = new URL(url);
    return `${parsed.origin}${parsed.pathname}${parsed.search}`;
  } catch {
    return url;
  }
}

function resolveHref(href) {
  if (href.startsWith("http://") || href.startsWith("https://")) {
    return href;
  }
  return `${baseUrl}${href.startsWith("/") ? href : `/${href}`}`;
}

async function auditPage(context, label, requestedUrl, meta = {}) {
  const page = await context.newPage();
  const consoleErrors = [];
  const pageErrors = [];
  const requestFailed = [];

  page.on("console", (msg) => {
    if (msg.type() === "error") {
      consoleErrors.push(msg.text().slice(0, 200));
    }
  });
  page.on("pageerror", (error) => {
    pageErrors.push(String(error.message).slice(0, 200));
  });
  page.on("requestfailed", (req) => {
    requestFailed.push(sanitizeUrl(req.url()));
  });

  let status = 0;
  let finalUrl = "";
  let title = "";
  let bodyText = "";

  try {
    const response = await page.goto(requestedUrl, { waitUntil: "domcontentloaded", timeout: 120_000 });
    status = response?.status() ?? 0;
    finalUrl = sanitizeUrl(page.url());
    title = await page.title();
    bodyText = await page.locator("body").innerText();
  } finally {
    await page.close();
  }

  const privateHits = scanTextForHits(bodyText + finalUrl, PRIVATE_ORIGIN_PATTERNS);
  const previewHits = scanTextForHits(bodyText, PREVIEW_RESIDUE_PATTERNS);
  const errorBoundary = /Dashboard unavailable|Dashboard temporarily unavailable/i.test(bodyText);

  let statusLabel = "PASS";
  if (status === 404 || status >= 500) statusLabel = "FAIL";
  if (status === 403 && !meta.expected403) statusLabel = "FAIL";
  if (privateHits.length > 0) statusLabel = "FAIL";
  if (previewHits.length > 0) statusLabel = "FAIL";
  if (errorBoundary) statusLabel = "FAIL";
  if (pageErrors.length > 0) statusLabel = "FAIL";

  const onPublicOrigin = finalUrl.startsWith(baseUrl);

  return {
    label,
    sourcePage: meta.sourcePage ?? null,
    href: meta.href ?? null,
    requestedUrl: sanitizeUrl(requestedUrl),
    finalUrl,
    httpStatus: status,
    pageTitle: title,
    consoleErrorCount: consoleErrors.length,
    pageErrorCount: pageErrors.length,
    requestFailedCount: requestFailed.length,
    privateOriginHit: privateHits.length > 0 || !onPublicOrigin ? "yes" : "no",
    previewResidueHit: previewHits.length > 0 ? "yes" : "no",
    errorBoundaryHit: errorBoundary ? "yes" : "no",
    status: statusLabel,
    previewPatterns: previewHits,
    privatePatterns: privateHits,
  };
}

async function collectSidebarLinks(context) {
  const page = await context.newPage();
  await page.goto(`${baseUrl}/admin/dashboard`, { waitUntil: "domcontentloaded", timeout: 120_000 });
  await page.waitForSelector("aside nav a", { timeout: 120_000 });

  const links = await page.locator("aside nav a").evaluateAll((anchors) =>
    anchors.map((anchor) => ({
      label: (anchor.textContent ?? "").replace(/\s+/g, " ").trim(),
      href: anchor.getAttribute("href") ?? "",
    })),
  );

  await page.close();

  return links.filter((link) => link.href !== "");
}

async function main() {
  if (!fs.existsSync(storagePath)) {
    console.error("ADMIN_PLAYWRIGHT_SESSION=MISSING — run jp-dash-03-admin-login-bootstrap.mjs first.");
    process.exit(1);
  }

  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ storageState: storagePath });

  const rows = [];
  const navRows = [];

  for (const route of NEXT_ADMIN_PAGES) {
    rows.push(await auditPage(context, route, resolveHref(route)));
  }

  for (const handoff of LARAVEL_ADMIN_HANDOFFS) {
    rows.push(await auditPage(context, handoff.label, resolveHref(handoff.href)));
  }

  const sidebarLinks = await collectSidebarLinks(context);
  for (const link of sidebarLinks) {
    const row = await auditPage(context, `sidebar:${link.label}`, resolveHref(link.href), {
      sourcePage: "/admin/dashboard",
      href: link.href,
    });
    navRows.push(row);
    rows.push(row);
  }

  await browser.close();

  const summary = {
    generatedAtUtc: new Date().toISOString(),
    baseUrl,
    total: rows.length,
    pass: rows.filter((r) => r.status === "PASS").length,
    fail: rows.filter((r) => r.status === "FAIL").length,
    rows,
  };

  const navSummary = {
    generatedAtUtc: summary.generatedAtUtc,
    baseUrl,
    total: navRows.length,
    pass: navRows.filter((r) => r.status === "PASS").length,
    fail: navRows.filter((r) => r.status === "FAIL").length,
    privateLaravelBrowserExposure:
      navRows.every((r) => r.privateOriginHit === "no") ? "PASS" : "FAIL",
    rows: navRows,
  };

  fs.mkdirSync(path.dirname(matrixPath), { recursive: true });
  fs.writeFileSync(matrixPath, JSON.stringify(summary, null, 2));
  fs.writeFileSync(navMatrixPath, JSON.stringify(navSummary, null, 2));

  console.log(`JP_DASH_03_CRAWL_PASS=${summary.pass}`);
  console.log(`JP_DASH_03_CRAWL_FAIL=${summary.fail}`);
  console.log(`PRIVATE_LARAVEL_BROWSER_EXPOSURE=${navSummary.privateLaravelBrowserExposure}`);
  console.log(`JP_DASH_03_PAGE_MATRIX=${path.basename(matrixPath)}`);

  if (summary.fail > 0) {
    process.exit(1);
  }
}

main().catch((error) => {
  console.error("JP_DASH_03_CRAWL_ERROR");
  console.error(error instanceof Error ? error.message : String(error));
  process.exit(1);
});
