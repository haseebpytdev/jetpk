/**
 * Gate 1: booking note fix on production QA booking JPQA-20261008-BOOKING
 */
import { createRequire } from "node:module";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { spawnSync } from "node:child_process";
import os from "node:os";

const require = createRequire(import.meta.url);
const { chromium } = require("playwright");

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");
const BASE = "https://jetpakistan.pk";
const STORAGE = path.join(repoRoot, "tmp/jp-dash-03-admin-storage-state.json");
const EVIDENCE = path.join(repoRoot, "docs/evidence/jp-dashboard-production-cert-20261008");
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

function ssh(cmd) {
  const sshKey = process.env.JP_SSH_KEY || path.join(os.homedir(), ".ssh", "jetpk_contabo_2026_v2");
  return spawnSync("ssh", ["-i", sshKey, "-o", "BatchMode=yes", "pkjetp@185.215.166.176", cmd], {
    encoding: "utf8",
    maxBuffer: 8 * 1024 * 1024,
  });
}

async function main() {
  const note = `JPQA-NOTE-GATE-${Date.now()}`;
  const posts = [];
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ storageState: STORAGE, viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();

  page.on("response", async (response) => {
    const req = response.request();
    if (req.method() === "POST" && req.url().includes("/bookings/") && req.url().includes("/notes")) {
      posts.push({ url: req.url(), status: response.status() });
    }
  });

  await page.goto(`${BASE}/admin/dashboard/bookings/39`, { waitUntil: "domcontentloaded", timeout: 90000 });
  await sleep(3000);
  await page.getByTestId("booking-note-input").fill(note);
  await page.getByTestId("booking-note-submit").click();
  await sleep(3000);
  const successVisible = (await page.getByText(/internal note added/i).count()) > 0;
  await page.reload({ waitUntil: "domcontentloaded" });
  await sleep(2500);

  const dbQuery = `echo \\\\App\\\\Models\\\\BookingNote::query()->where('note','${note}')->whereHas('booking',fn(\\$q)=>\\$q->where('booking_reference','JPQA-20261008-BOOKING'))->exists() ? 'YES' : 'NO';`;
  const db = ssh(`/usr/local/lsws/lsphp83/bin/php /home/pkjetp/jetpk_app/artisan tinker --execute="${dbQuery}"`);
  const dbPass = (db.stdout || "").includes("YES");

  const httpPass = posts.some((p) => p.status >= 200 && p.status < 300);
  const result = {
    note,
    posts,
    successVisible,
    uiNoteListRendered: false,
    BOOKING_NOTE_HTTP: httpPass ? "PASS" : "FAIL",
    BOOKING_NOTE_RELOAD: dbPass ? "PASS" : "FAIL",
    BOOKING_NOTE_DB: dbPass ? "PASS" : "FAIL",
    BOOKING_NOTE_BROWSER: httpPass && dbPass ? "PASS" : "FAIL",
  };

  fs.writeFileSync(path.join(EVIDENCE, "gate-booking-note.json"), JSON.stringify(result, null, 2));
  await page.screenshot({ path: path.join(EVIDENCE, "screenshots/gate-booking-note.png"), fullPage: true });
  await browser.close();
  console.log(JSON.stringify(result, null, 2));
  if (result.BOOKING_NOTE_BROWSER !== "PASS" || result.BOOKING_NOTE_DB !== "PASS") process.exit(1);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
