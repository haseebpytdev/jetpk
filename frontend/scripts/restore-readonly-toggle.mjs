/** Restore READ_ONLY_FLIGHT_SEARCH=ON after partial continuation crash. */
import { chromium } from "playwright";
import { getStoragePath } from "../../dashboard/scripts/jp-dash-03-acceptance/auth-storage.mjs";
import { probeEffectiveReadOnly, readAdminToggleStates, setAdminToggles } from "./live-readonly-qa-helpers.mjs";

async function main() {
  const browser = await chromium.launch({ headless: true });
  const ctx = await browser.newContext({ storageState: getStoragePath("admin") });
  const page = await ctx.newPage();
  try {
    await page.goto("https://jetpakistan.pk/admin/settings/ai-assistant", { waitUntil: "domcontentloaded", timeout: 120_000 });
    const before = await readAdminToggleStates(page);
    if (!before.states.flight_search_read_only_enabled) {
      await setAdminToggles(page, { flight_search_read_only_enabled: true });
      await page.reload({ waitUntil: "domcontentloaded" });
    }
    const after = await readAdminToggleStates(page);
    const eff = await probeEffectiveReadOnly(page);
    console.log(JSON.stringify({
      restored: after.states.flight_search_read_only_enabled === true,
      effective: eff.effective?.flight_search_read_only_enabled === true,
      states: after.states,
    }, null, 2));
  } finally {
    await ctx.close();
    await browser.close();
  }
}

main().catch((e) => { console.error(e); process.exit(1); });
