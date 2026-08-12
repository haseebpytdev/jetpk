/**
 * Probe: can an authenticated user reach their portal from visible chrome
 * (avatar/name chip) without route knowledge?
 */
import { chromium } from "@playwright/test";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../..");
const baseUrl = process.env.JP_ACCEPTANCE_BASE_URL ?? "https://jetpakistan.pk";
const outDir = path.join(repoRoot, "tmp/jp-uat-01");
fs.mkdirSync(outDir, { recursive: true });

const roles = ["customer", "agent", "staff", "admin"];
const results = [];

const browser = await chromium.launch({ headless: true });
for (const role of roles) {
  const storage = path.join(repoRoot, `tmp/jp-dash-03-${role}-storage-state.json`);
  const row = { role, ok: false, steps: [], portalNav: [], findings: [] };
  if (!fs.existsSync(storage)) {
    row.findings.push("storage_missing");
    results.push(row);
    continue;
  }
  const context = await browser.newContext({ storageState: storage, viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();
  try {
    await page.goto(`${baseUrl}/`, { waitUntil: "domcontentloaded", timeout: 60000 });
    row.steps.push({ url: page.url() });

    // Visible identity chip / menu (business user clicks their name)
    const chip = page.getByText(/JP-DASH-03 QA/i).first();
    const chipAlt = page.locator("header, nav").getByRole("button").filter({ hasText: /JP-|QA |Customer|Agent|Staff|Admin/i }).first();
    let opened = false;
    if ((await chip.count()) > 0) {
      await chip.click({ timeout: 5000 });
      opened = true;
      row.steps.push({ click: "identity_text_chip" });
    } else if ((await chipAlt.count()) > 0) {
      await chipAlt.click({ timeout: 5000 });
      opened = true;
      row.steps.push({ click: "identity_button" });
    }
    await page.waitForTimeout(800);

    // Menu items after chip
    const menuTexts = await page.locator('[role="menu"] a, [role="menu"] button, [data-radix-menu-content] a, .dropdown-menu a, header a, header button').allTextContents().catch(() => []);
    row.menuSample = menuTexts.map((t) => t.replace(/\s+/g, " ").trim()).filter(Boolean).slice(0, 40);

    const portalLink = page.getByRole("link", { name: /dashboard|my account|portal|bookings|agent home|staff|admin/i }).first();
    const portalBtn = page.getByRole("button", { name: /dashboard|my account|portal|go to/i }).first();
    if ((await portalLink.count()) > 0) {
      const label = await portalLink.innerText();
      await portalLink.click();
      row.steps.push({ click: label.trim() });
    } else if ((await portalBtn.count()) > 0) {
      const label = await portalBtn.innerText();
      await portalBtn.click();
      row.steps.push({ click: label.trim() });
    } else {
      // try any link containing dashboard path text
      const anyDash = page.locator("a").filter({ hasText: /dashboard|my bookings|wallet|support tickets|live operations/i }).first();
      if ((await anyDash.count()) > 0) {
        const label = await anyDash.innerText();
        await anyDash.click();
        row.steps.push({ click: label.trim() });
      } else {
        row.findings.push("no_visible_portal_entry_after_identity");
      }
    }
    await page.waitForTimeout(2000);
    row.finalUrl = page.url();
    row.finalPath = new URL(page.url()).pathname;
    const body = (await page.locator("body").innerText()).replace(/\s+/g, " ").slice(0, 1500);
    row.snippet = body;
    row.portalNav = (
      await page.locator('nav a, [data-testid="portal-sidebar"] a, aside a').allTextContents().catch(() => [])
    )
      .map((t) => t.replace(/\s+/g, " ").trim())
      .filter(Boolean)
      .slice(0, 40);

    const inPortal =
      /\/(customer|agent|staff|admin)\//.test(row.finalPath) ||
      /live operations|my bookings|wallet|support tickets|ops inbox|assigned/i.test(body);
    row.ok = inPortal;
    if (!opened) row.findings.push("identity_chip_not_found");
    if (!inPortal) row.findings.push("did_not_reach_role_portal");

    // Public support form control check (once)
    if (role === "customer") {
      await page.goto(`${baseUrl}/support`, { waitUntil: "domcontentloaded" });
      const submit = page.getByRole("button", { name: /submit support request/i });
      row.publicSupportSubmitCount = await submit.count();
      if (row.publicSupportSubmitCount > 0) {
        const enabled = await submit.first().isEnabled().catch(() => false);
        const box = await submit.first().boundingBox().catch(() => null);
        row.publicSupportSubmit = { enabled, box };
        // Try fill minimal and see validation (do not spam if it would email prod indiscriminately —
        // only check client validation by empty submit)
        await submit.first().click({ timeout: 5000 }).catch((e) => {
          row.findings.push(`public_support_submit_click_fail:${String(e).slice(0, 80)}`);
        });
        await page.waitForTimeout(500);
        row.publicSupportAfterClick = (await page.locator("body").innerText()).replace(/\s+/g, " ").slice(0, 400);
      }
    }
  } catch (e) {
    row.findings.push(String(e).slice(0, 200));
  } finally {
    await context.close();
  }
  results.push(row);
  console.log(`ROLE=${role} OK=${row.ok ? "yes" : "no"} PATH=${row.finalPath || ""} FINDINGS=${row.findings.length}`);
}

await browser.close();
const out = path.join(outDir, `portal-entry-probe-${Date.now()}.json`);
fs.writeFileSync(out, JSON.stringify(results, null, 2));
console.log(`REPORT_PATH=${out}`);
