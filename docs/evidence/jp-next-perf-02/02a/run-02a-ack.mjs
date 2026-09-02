/**
 * USER_ACTION_TO_ACK — time from click to groups-search-progress / Searching label.
 */
import { chromium } from "../../../../frontend/node_modules/playwright/index.mjs";
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
function pct(arr, p) {
  const a = [...arr].sort((x, y) => x - y);
  return a[Math.min(a.length - 1, Math.max(0, Math.ceil((p / 100) * a.length) - 1))];
}

async function main() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  const samples = [];

  for (let i = 0; i < 20; i++) {
    await page.goto("https://jetpakistan.pk/groups", { waitUntil: "domcontentloaded", timeout: 60000 });
    // ensure search form ready
    await page.waitForSelector('[data-testid="groups-landing-search"]', { timeout: 30000 });
    await page.waitForTimeout(200);
    // fill required fields if empty — use evaluate to set selects/inputs if present
    await page.evaluate(() => {
      const selects = Array.from(document.querySelectorAll("select"));
      for (const s of selects) {
        if (!s.value && s.options.length > 1) {
          s.value = s.options[1].value;
          s.dispatchEvent(new Event("change", { bubbles: true }));
        }
      }
    });
    await page.waitForTimeout(100);
    const ms = await page.evaluate(async () => {
      const btn = Array.from(document.querySelectorAll("button")).find((b) =>
        /Search Groups|Search group|Search/i.test(b.textContent || ""),
      );
      if (!btn) return -1;
      const t0 = performance.now();
      btn.click();
      return await new Promise((resolve) => {
        const start = performance.now();
        const tick = () => {
          const el = document.querySelector('[data-testid="groups-search-progress"]');
          const txt = el?.textContent || "";
          if (/Searching|Checking|Finding/i.test(txt)) {
            resolve(Math.round(performance.now() - t0));
            return;
          }
          if (performance.now() - start > 2000) {
            resolve(Math.round(performance.now() - t0));
            return;
          }
          requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);
      });
    });
    samples.push(ms);
    console.log("ack", i, ms);
    // allow navigation settle / abort to next loop
    await page.waitForTimeout(400);
  }

  const valid = samples.filter((n) => n >= 0);
  const out = {
    USER_ACTION_TO_ACK_P50_MS: pct(valid, 50),
    USER_ACTION_TO_ACK_P95_MS: pct(valid, 95),
    samples: valid,
    definition: "click Search → groups-search-progress Searching/Checking/Finding visible (rAF poll)",
  };
  fs.writeFileSync(path.join(__dirname, "user-action-ack.json"), JSON.stringify(out, null, 2));
  await browser.close();
  console.log("ACK_DONE", out.USER_ACTION_TO_ACK_P50_MS, out.USER_ACTION_TO_ACK_P95_MS);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
