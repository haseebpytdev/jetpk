/**
 * Black-box RBAC boundary + adversarial public exploration (no commercial mutations).
 */
import { chromium } from "@playwright/test";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../..");
const outDir = path.join(repoRoot, "tmp/jp-uat-01");
const baseUrl = process.env.JP_ACCEPTANCE_BASE_URL ?? "https://jetpakistan.pk";
fs.mkdirSync(outDir, { recursive: true });

const report = { rbac: [], adversarial: {}, success: false };

async function probe(role, targets) {
  const storage = path.join(repoRoot, `tmp/jp-dash-03-${role}-storage-state.json`);
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ storageState: storage, viewport: { width: 1366, height: 900 } });
  const page = await context.newPage();
  const row = { role, attempts: [] };
  try {
    for (const target of targets) {
      const res = await page.goto(`${baseUrl}${target}`, { waitUntil: "domcontentloaded", timeout: 45000 });
      await page.waitForTimeout(800);
      const status = res?.status() ?? 0;
      const pathName = new URL(page.url()).pathname;
      const body = (await page.locator("body").innerText().catch(() => "")).replace(/\s+/g, " ").slice(0, 400);
      const leaked = /platform admin console|staff permission matrix|all agencies|supplier token/i.test(body);
      const denied =
        status === 403 ||
        /login|unauthorized|forbidden|not authorized|access denied/i.test(body) ||
        /\/login/.test(pathName) ||
        (role === "customer" && /\/admin\//.test(target) && !/\/admin\//.test(pathName));
      row.attempts.push({ target, status, pathName, denied, leaked });
      if (leaked) report.findings = [...(report.findings || []), { severity: "P0", role, target }];
    }
  } finally {
    await browser.close();
  }
  report.rbac.push(row);
}

await probe("customer", ["/admin/dashboard", "/staff/dashboard", "/agent/dashboard"]);
await probe("agent", ["/admin/dashboard", "/staff/dashboard", "/customer/dashboard"]);
await probe("staff", ["/admin/dashboard/settings", "/admin/suppliers"]);

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
const dead = [];
try {
  await page.goto(`${baseUrl}/`, { waitUntil: "domcontentloaded" });
  const links = await page.locator("footer a, nav a").evaluateAll((nodes) =>
    nodes.slice(0, 25).map((n) => ({ href: n.getAttribute("href") || "", text: (n.textContent || "").trim().slice(0, 40) })),
  );
  for (const link of links) {
    if (!link.href || link.href.startsWith("http") && !link.href.includes("jetpakistan.pk")) continue;
    if (link.href.startsWith("mailto:") || link.href.startsWith("tel:")) continue;
    const url = link.href.startsWith("http") ? link.href : new URL(link.href, baseUrl).toString();
    const res = await page.goto(url, { waitUntil: "domcontentloaded", timeout: 30000 }).catch(() => null);
    const status = res?.status() ?? 0;
    if (status >= 500 || status === 404) dead.push({ href: link.href, status, text: link.text });
    const body = (await page.locator("body").innerText().catch(() => "")).slice(0, 200);
    if (/parwaaz|yoursdomain|haseeb-master/i.test(body)) dead.push({ href: link.href, note: "legacy_brand" });
  }
  report.adversarial = { linksChecked: links.length, deadEnds: dead };
} finally {
  await browser.close();
}

const unauthorizedLeak = (report.findings || []).some((f) => f.severity === "P0");
const customerDenied = report.rbac.find((r) => r.role === "customer")?.attempts.every((a) => a.denied && !a.leaked);
report.success = !unauthorizedLeak && customerDenied && dead.length === 0;
const out = path.join(outDir, `rbac-adversarial-${Date.now()}.json`);
fs.writeFileSync(out, JSON.stringify(report, null, 2));
console.log(`REPORT_PATH=${out}`);
console.log(`SUCCESS=${report.success ? "yes" : "no"}`);
console.log(`DEAD_ENDS=${dead.length}`);
console.log(`CUSTOMER_DENIED=${customerDenied ? "yes" : "no"}`);
