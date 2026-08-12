/**
 * JP-UAT-01 authenticated black-box persona runner + light deterministic checks.
 * Starts at public home with storage state (already logged in) — no route coaching.
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

const role = (process.argv[2] || "customer").toLowerCase();
const storage = {
  admin: path.join(repoRoot, "tmp/jp-dash-03-admin-storage-state.json"),
  staff: path.join(repoRoot, "tmp/jp-dash-03-staff-storage-state.json"),
  agent: path.join(repoRoot, "tmp/jp-dash-03-agent-storage-state.json"),
  customer: path.join(repoRoot, "tmp/jp-dash-03-customer-storage-state.json"),
}[role];

if (!storage || !fs.existsSync(storage)) {
  console.error(`STORAGE_MISSING=${role}`);
  process.exit(1);
}

const goals = {
  customer: {
    keywords: ["support", "help", "ticket", "request", "message", "account", "booking", "inbox"],
    successRe: /support|help|ticket|request|message/i,
    forbiddenRe: /internal note|staff only|assignee id|ops_inbox/i,
  },
  agent: {
    keywords: ["booking", "deposit", "wallet", "ledger", "request", "finance", "support", "notification"],
    successRe: /booking|deposit|wallet|request|ledger|agent/i,
    forbiddenRe: /platform admin|staff operator|all agencies/i,
  },
  staff: {
    keywords: ["support", "operations", "inbox", "assigned", "ticket", "booking", "notification", "live"],
    successRe: /support|ticket|operations|inbox|assigned|booking/i,
    forbiddenRe: /supplier token|generate token|otp_demo/i,
  },
  admin: {
    keywords: ["support", "operations", "assign", "staff", "inbox", "health", "booking", "agent", "live"],
    successRe: /support|operations|inbox|assign|booking|staff|health/i,
    forbiddenRe: /parwaaz|yoursdomain/i,
  },
};

const goal = goals[role];
const telemetry = {
  role,
  start: `${baseUrl}/`,
  success: false,
  actions: [],
  nav: [],
  deadEnds: [],
  unexpectedPermissions: [],
  confusing: [],
  visibleLabels: [],
  findings: [],
  finalUrl: "",
  snippet: "",
};

function wordScore(text, keywords) {
  const t = text.toLowerCase();
  let s = 0;
  for (const kw of keywords) {
    const escaped = kw.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
    if (new RegExp(`(?:^|[^a-z0-9])${escaped}(?:[^a-z0-9]|$)`, "i").test(t)) s += 2;
  }
  if (/theme|facebook|twitter|logout|sign out|cookie/i.test(t)) return 0;
  return s;
}

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({ storageState: storage, viewport: { width: 1440, height: 900 } });
const page = await context.newPage();
page.on("response", (res) => {
  if (res.status() >= 500 && res.request().resourceType() === "document") {
    telemetry.deadEnds.push({ type: "5xx", url: res.url().split("?")[0], status: res.status() });
  }
  if (res.status() === 404 && res.request().resourceType() === "document") {
    telemetry.deadEnds.push({ type: "404", url: res.url().split("?")[0] });
  }
});

try {
  await page.goto(`${baseUrl}/`, { waitUntil: "domcontentloaded", timeout: 60000 });
  telemetry.nav.push(page.url());

  // Prefer account/portal entry from visible chrome
  const accountCandidates = [
    page.getByRole("link", { name: /my account|account|dashboard|portal/i }).first(),
    page.getByRole("button", { name: /my account|account|dashboard|portal/i }).first(),
  ];
  for (const c of accountCandidates) {
    if ((await c.count()) > 0) {
      const label = (await c.innerText().catch(() => "")) || (await c.getAttribute("aria-label")) || "";
      await c.click({ timeout: 5000 }).catch(() => {});
      await page.waitForTimeout(1200);
      telemetry.actions.push({ type: "enter_account", label });
      telemetry.nav.push(page.url());
      break;
    }
  }

  const visited = new Set();
  for (let i = 0; i < 24; i++) {
    const body = (await page.locator("body").innerText().catch(() => "")).replace(/\s+/g, " ");
    telemetry.snippet = body.slice(0, 1800);
    if (goal.forbiddenRe.test(body)) {
      telemetry.findings.push({ severity: "P0", note: "forbidden_content_visible", url: page.url() });
    }
    if (/403|forbidden|unauthorized/i.test(body) && !/login/i.test(page.url())) {
      telemetry.unexpectedPermissions.push({ url: page.url() });
    }
    if (/coming soon|lorem ipsum|under construction|preview residue/i.test(body)) {
      telemetry.confusing.push({ url: page.url(), note: "placeholder" });
    }

    const items = await page.evaluate(() =>
      [...document.querySelectorAll("a, button, [role='button']")]
        .filter((el) => {
          const r = el.getBoundingClientRect();
          const st = getComputedStyle(el);
          return r.width > 2 && r.height > 2 && st.visibility !== "hidden" && st.display !== "none";
        })
        .map((el) => ({
          text: (el.getAttribute("aria-label") || el.innerText || "").replace(/\s+/g, " ").trim().slice(0, 100),
          href: el.tagName === "A" ? el.getAttribute("href") || "" : "",
          tag: el.tagName.toLowerCase(),
        }))
        .filter((x) => x.text),
    );

    telemetry.visibleLabels = [...new Set([...telemetry.visibleLabels, ...items.map((x) => x.text)])].slice(0, 80);

    const ranked = items
      .map((it) => ({ ...it, score: wordScore(it.text, goal.keywords) }))
      .filter((it) => it.score > 0)
      .sort((a, b) => b.score - a.score);

    let clicked = false;
    for (const cand of ranked.slice(0, 10)) {
      const key = `${cand.text}|${cand.href}`;
      if (visited.has(key)) continue;
      visited.add(key);
      const loc = page.getByRole(cand.tag === "a" ? "link" : "button", { name: cand.text, exact: false }).first();
      try {
        if ((await loc.count()) === 0) continue;
        await loc.click({ timeout: 4000 });
        await page.waitForTimeout(1000);
        telemetry.actions.push({ click: cand.text, href: cand.href });
        telemetry.nav.push(page.url());
        clicked = true;
        break;
      } catch (e) {
        telemetry.deadEnds.push({ text: cand.text, error: String(e).slice(0, 120) });
      }
    }
    if (!clicked) break;
    if (goal.successRe.test(telemetry.snippet) && telemetry.actions.length >= 2) {
      // keep exploring a little for dead ends, then allow success
    }
  }

  telemetry.finalUrl = page.url();
  const joined = `${telemetry.snippet} ${telemetry.visibleLabels.join(" ")} ${telemetry.nav.join(" ")}`;
  telemetry.success = goal.successRe.test(joined) && telemetry.findings.every((f) => f.severity !== "P0");
  telemetry.deadEndCount = telemetry.deadEnds.length;
} finally {
  const out = path.join(outDir, `auth-persona-${role}-${Date.now()}.json`);
  fs.writeFileSync(out, JSON.stringify(telemetry, null, 2));
  console.log(`REPORT_PATH=${out}`);
  console.log(`ROLE=${role}`);
  console.log(`SUCCESS=${telemetry.success ? "yes" : "no"}`);
  console.log(`ACTIONS=${telemetry.actions.length}`);
  console.log(`DEAD_ENDS=${telemetry.deadEnds.length}`);
  console.log(`FINDINGS=${telemetry.findings.length}`);
  console.log(`FINAL_URL_PATH=${(() => { try { return new URL(telemetry.finalUrl).pathname; } catch { return ""; } })()}`);
  await browser.close();
}
