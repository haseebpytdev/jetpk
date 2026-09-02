/**
 * ACK remasure with proper group form fill + submit button testid.
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
    await page.waitForSelector('[data-testid="group-search-submit"]', { timeout: 30000 });
    await page.waitForFunction(
      () => document.querySelectorAll('[data-testid="group-sector-select"] option').length > 1,
      { timeout: 30000 },
    );
    await page.selectOption('[data-testid="group-airline-select"]', { index: 1 });
    await page.selectOption('[data-testid="group-sector-select"]', "ISB-SHJ");
    await page.waitForTimeout(80);

    const ms = await page.evaluate(async () => {
      const btn = document.querySelector('[data-testid="group-search-submit"]');
      if (!btn) return -1;
      const t0 = performance.now();
      btn.dispatchEvent(new MouseEvent("click", { bubbles: true, cancelable: true }));
      // also native click
      btn.click();
      return await new Promise((resolve) => {
        let done = false;
        const finish = (v) => {
          if (done) return;
          done = true;
          resolve(v);
        };
        const obs = new MutationObserver(() => {
          const el = document.querySelector('[data-testid="groups-search-progress"]');
          if (el && /Searching|Checking|Finding/i.test(el.textContent || "")) {
            finish(Math.round(performance.now() - t0));
            obs.disconnect();
          }
        });
        obs.observe(document.body, { childList: true, subtree: true, characterData: true });
        // also poll disabled state / aria-busy
        const tick = () => {
          const el = document.querySelector('[data-testid="groups-search-progress"]');
          if (el && /Searching|Checking|Finding/i.test(el.textContent || "")) {
            finish(Math.round(performance.now() - t0));
            return;
          }
          if (btn.disabled || btn.getAttribute("aria-busy") === "true") {
            finish(Math.round(performance.now() - t0));
            return;
          }
          if (performance.now() - t0 > 500) {
            finish(Math.round(performance.now() - t0));
            return;
          }
          requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);
      });
    });
    samples.push(ms);
    console.log("ack", i, ms);
    await page.waitForTimeout(350);
  }

  const valid = samples.filter((n) => n >= 0 && n < 500);
  const out = {
    USER_ACTION_TO_ACK_P50_MS: pct(valid, 50),
    USER_ACTION_TO_ACK_P95_MS: pct(valid, 95),
    samples: valid,
    raw_samples: samples,
    definition: "filled airline+sector → click group-search-submit → groups-search-progress or disabled ack",
    valid_n: valid.length,
  };
  fs.writeFileSync(path.join(__dirname, "user-action-ack.json"), JSON.stringify(out, null, 2));
  await browser.close();
  console.log("ACK_DONE", out.valid_n, out.USER_ACTION_TO_ACK_P50_MS, out.USER_ACTION_TO_ACK_P95_MS);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
