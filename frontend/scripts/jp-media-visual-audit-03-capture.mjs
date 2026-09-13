#!/usr/bin/env node
/**
 * Production visual media audit capture for JP-MEDIA-VISUAL-AUDIT-03.
 */
import { chromium } from "playwright";
import { mkdirSync, writeFileSync } from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "..", "..");
const evidenceRoot = path.join(repoRoot, "docs", "evidence", "jp-media-visual-audit-03", "production");

const PAGES = [
  { id: "home", url: "https://jetpakistan.pk/", label: "Homepage" },
  { id: "login", url: "https://jetpakistan.pk/login", label: "Login" },
  { id: "register", url: "https://jetpakistan.pk/register", label: "Register" },
  { id: "forgot-password", url: "https://jetpakistan.pk/forgot-password", label: "Forgot password" },
  { id: "login-otp", url: "https://jetpakistan.pk/login/otp", label: "Login OTP" },
  { id: "agent-register", url: "https://jetpakistan.pk/agent/register", label: "Agent register" },
  { id: "lookup-booking", url: "https://jetpakistan.pk/lookup-booking", label: "Booking lookup" },
  { id: "support", url: "https://jetpakistan.pk/support", label: "Support" },
  { id: "groups-search", url: "https://jetpakistan.pk/groups/search", label: "Groups search" },
  { id: "verify-email", url: "https://jetpakistan.pk/verify-email", label: "Verify email" },
  { id: "not-found", url: "https://jetpakistan.pk/this-page-does-not-exist-jp-audit", label: "404" },
  { id: "access-denied", url: "https://jetpakistan.pk/access-denied?reason=not-found", label: "Access denied" },
];

const VIEWPORTS = [
  { name: "desktop", width: 1280, height: 800 },
  { name: "mobile", width: 390, height: 844 },
];

mkdirSync(path.join(evidenceRoot, "desktop"), { recursive: true });
mkdirSync(path.join(evidenceRoot, "mobile"), { recursive: true });

const report = [];

const browser = await chromium.launch({ headless: true });
for (const vp of VIEWPORTS) {
  const context = await browser.newContext({ viewport: { width: vp.width, height: vp.height } });
  const page = await context.newPage();
  for (const entry of PAGES) {
    try {
      await page.goto(entry.url, { waitUntil: "networkidle", timeout: 60000 });
      await page.waitForTimeout(1200);
      const shot = path.join(evidenceRoot, vp.name, `${entry.id}.png`);
      await page.screenshot({ path: shot, fullPage: true });
      const images = await page.evaluate(async () => {
        const rows = [];
        for (const img of Array.from(document.querySelectorAll("img"))) {
          const rect = img.getBoundingClientRect();
          if (rect.width < 24 || rect.height < 24) continue;
          let status = null;
          try {
            const r = await fetch(img.currentSrc || img.src, { method: "HEAD" });
            status = r.status;
          } catch {
            status = "error";
          }
          rows.push({
            src: img.currentSrc || img.src,
            alt: img.alt,
            natural: `${img.naturalWidth}x${img.naturalHeight}`,
            displayed: `${Math.round(rect.width)}x${Math.round(rect.height)}`,
            status,
            testId: img.closest("[data-testid]")?.getAttribute("data-testid") ?? null,
          });
        }
        const authPanel = document.querySelector('[data-testid="auth-illustration-panel"]');
        const authImg = authPanel?.querySelector("img");
        return {
          title: document.title,
          finalUrl: location.href,
          authIllustrationVisible: !!authImg,
          authIllustrationSrc: authImg?.src ?? null,
          images: rows,
        };
      });
      report.push({ ...entry, viewport: vp.name, screenshot: shot, ...images });
      console.log(`[capture] ${vp.name} ${entry.id} images=${images.images.length} auth=${images.authIllustrationVisible}`);
    } catch (err) {
      report.push({ ...entry, viewport: vp.name, error: String(err) });
      console.error(`[capture] FAIL ${vp.name} ${entry.id}:`, err.message);
    }
  }
  await context.close();
}
await browser.close();

writeFileSync(path.join(evidenceRoot, "capture-report.json"), JSON.stringify(report, null, 2));
console.log(`Wrote ${report.length} capture rows`);
