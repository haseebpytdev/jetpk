/**
 * JP-UAT-01 black-box business persona explorer (Playwright CLI).
 * Goal-driven visible-UI navigation only — no route coaching, no test-id nav.
 *
 * Usage:
 *   node dashboard/scripts/jp-uat-01/black-box-persona.mjs <persona> [storageStatePath]
 *
 * Personas: anonymous | customer | agent | staff | admin | adversarial
 * Writes telemetry JSON under tmp/jp-uat-01/ (no secrets).
 */
import { chromium } from "@playwright/test";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../..");
const outDir = path.join(repoRoot, "tmp/jp-uat-01");
const baseUrl = process.env.JP_ACCEPTANCE_BASE_URL ?? "https://jetpakistan.pk";

const PERSONAS = {
  anonymous: {
    start: `${baseUrl}/`,
    goal: "Find a suitable flight and understand fare information. Stop before creating a booking.",
    keywords: [
      "flight",
      "search",
      "book",
      "one way",
      "return",
      "from",
      "to",
      "depart",
      "passenger",
      "results",
      "fare",
      "filter",
      "direct",
    ],
    stopWords: ["pay now", "confirm booking", "issue ticket", "purchase"],
    maxActions: 28,
    allowLiveSearch: true,
  },
  customer: {
    start: `${baseUrl}/`,
    goal: "From your account area, find Support, open or create help, understand status.",
    keywords: [
      "account",
      "my account",
      "dashboard",
      "support",
      "help",
      "ticket",
      "request",
      "message",
      "inbox",
      "open",
      "resolved",
      "reply",
    ],
    stopWords: ["admin", "staff settings", "wallet credit"],
    maxActions: 36,
  },
  agent: {
    start: `${baseUrl}/`,
    goal: "Understand agent work areas, bookings/requests, finance request surfaces without moving money.",
    keywords: [
      "agent",
      "booking",
      "request",
      "deposit",
      "wallet",
      "ledger",
      "finance",
      "notification",
      "support",
      "help",
      "balance",
    ],
    stopWords: ["approve deposit", "credit wallet", "debit"],
    maxActions: 36,
  },
  staff: {
    start: `${baseUrl}/`,
    goal: "Start shift: find what needs attention, assigned work, support vs bookings, safe next action.",
    keywords: [
      "live operations",
      "operations",
      "inbox",
      "assigned",
      "support",
      "ticket",
      "booking",
      "notification",
      "attention",
      "queue",
      "reply",
      "note",
    ],
    stopWords: ["refund", "void ticket", "approve payment"],
    maxActions: 40,
  },
  admin: {
    start: `${baseUrl}/`,
    goal: "Start day: operational attention, new customer request, assign staff, monitor health.",
    keywords: [
      "live operations",
      "operations",
      "dashboard",
      "support",
      "assign",
      "staff",
      "inbox",
      "notification",
      "system health",
      "go-live",
      "booking",
      "agent",
      "audit",
    ],
    stopWords: ["generate token", "activate supplier", "refund"],
    maxActions: 40,
  },
  adversarial: {
    start: `${baseUrl}/`,
    goal: "Explore visible workflows for confusion, dead ends, misleading state. No commercial mutations.",
    keywords: [
      "support",
      "booking",
      "operations",
      "settings",
      "help",
      "account",
      "agent",
      "payment",
      "request",
    ],
    stopWords: ["pay now", "confirm booking", "issue ticket", "approve deposit"],
    maxActions: 45,
  },
};

function scoreCandidate(text, keywords) {
  const t = text.toLowerCase().trim();
  if (!t || t.length > 80) return 0;
  // Avoid accidental substring hits (e.g. "to" inside "to switch theme").
  let score = 0;
  for (const kw of keywords) {
    const escaped = kw.toLowerCase().replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
    const re = new RegExp(`(?:^|[^a-z0-9])${escaped}(?:[^a-z0-9]|$)`, "i");
    if (re.test(t)) score += kw.length > 8 ? 3 : 2;
  }
  if (/theme|facebook|twitter|instagram|linkedin|youtube|cookie/i.test(t)) {
    score = 0;
  }
  return score;
}

async function inventory(page) {
  return page.evaluate(() => {
    const items = [];
    const nodes = document.querySelectorAll("a, button, [role='button'], input[type='submit']");
    for (const el of nodes) {
      const style = window.getComputedStyle(el);
      if (style.display === "none" || style.visibility === "hidden") continue;
      const rect = el.getBoundingClientRect();
      if (rect.width < 2 || rect.height < 2) continue;
      const text = (
        el.getAttribute("aria-label") ||
        el.innerText ||
        el.getAttribute("title") ||
        el.getAttribute("value") ||
        el.getAttribute("name") ||
        ""
      )
        .replace(/\s+/g, " ")
        .trim();
      if (!text) continue;
      items.push({
        tag: el.tagName.toLowerCase(),
        text: text.slice(0, 120),
        href: el.tagName.toLowerCase() === "a" ? el.getAttribute("href") || "" : "",
      });
    }
    return items;
  });
}

async function pageSignals(page) {
  const url = page.url();
  const title = await page.title().catch(() => "");
  const bodyText = await page.locator("body").innerText().catch(() => "");
  const clipped = bodyText.replace(/\s+/g, " ").trim().slice(0, 2500);
  const headings = await page.locator("h1,h2,h3").allTextContents().catch(() => []);
  const statusCodeHint = await page
    .locator("text=/\\b(404|500|403|Not Found|Server Error)\\b/i")
    .count()
    .catch(() => 0);
  return { url, title, headings: headings.map((h) => h.trim()).filter(Boolean).slice(0, 20), snippet: clipped, statusCodeHint };
}

async function tryFillSearchIfPresent(page, telemetry) {
  // Visible public search only — no deep route knowledge.
  const from = page.getByLabel(/from|origin|departure city/i).first();
  const to = page.getByLabel(/to|destination|arrival city/i).first();
  const searchBtn = page.getByRole("button", { name: /search|find flights|show flights/i }).first();

  const fromCount = await from.count().catch(() => 0);
  const toCount = await to.count().catch(() => 0);
  const btnCount = await searchBtn.count().catch(() => 0);
  if (!fromCount || !toCount || !btnCount) {
    // fallback placeholders
    const placeholders = page.locator("input[placeholder*='From' i], input[placeholder*='Origin' i], input[name*='origin' i]");
    const dest = page.locator("input[placeholder*='To' i], input[placeholder*='Destination' i], input[name*='destination' i]");
    if ((await placeholders.count()) === 0 || (await dest.count()) === 0) {
      return { attempted: false, reason: "search_controls_not_found" };
    }
  }

  telemetry.actions.push({ type: "observe", note: "search_form_visible" });
  return { attempted: false, reason: "form_visible_awaiting_safe_fill", formVisible: true };
}

async function clickBest(page, persona, visited, telemetry) {
  const items = await inventory(page);
  const ranked = items
    .map((it) => ({ ...it, score: scoreCandidate(it.text, persona.keywords) }))
    .filter((it) => it.score > 0)
    .sort((a, b) => b.score - a.score);

  for (const candidate of ranked.slice(0, 12)) {
    const key = `${candidate.text}|${candidate.href}`;
    if (visited.has(key)) continue;
    const lower = candidate.text.toLowerCase();
    if (persona.stopWords.some((s) => lower.includes(s))) {
      telemetry.wrongChoices.push({ text: candidate.text, reason: "commercial_stopword" });
      visited.add(key);
      continue;
    }
    visited.add(key);
    const locator = page.getByRole(candidate.tag === "a" ? "link" : "button", { name: candidate.text, exact: false }).first();
    try {
      if ((await locator.count()) === 0) continue;
      await locator.click({ timeout: 4000 });
      await page.waitForTimeout(900);
      telemetry.actions.push({ type: "click", text: candidate.text, href: candidate.href, score: candidate.score });
      telemetry.navigationTransitions.push(page.url());
      return true;
    } catch (err) {
      telemetry.deadEnds.push({ text: candidate.text, error: String(err).slice(0, 160) });
    }
  }
  return false;
}

async function runPersona(name) {
  const persona = PERSONAS[name];
  if (!persona) throw new Error(`unknown persona ${name}`);

  fs.mkdirSync(outDir, { recursive: true });
  const storageArg = process.argv[3];
  const started = Date.now();
  const telemetry = {
    persona: name,
    goal: persona.goal,
    start: persona.start,
    success: false,
    elapsedSeconds: 0,
    browserActions: 0,
    actions: [],
    navigationTransitions: [],
    backtracks: 0,
    wrongChoices: [],
    deadEnds: [],
    confusingLabels: [],
    unexpectedPermissions: [],
    errorMessages: [],
    hiddenKnowledgeRequired: false,
    finalUserConfidence: "low",
    pageSnapshots: [],
    findings: [],
    liveSearchAttempted: false,
  };

  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext(
    storageArg && fs.existsSync(storageArg) ? { storageState: storageArg } : {},
  );
  const page = await context.newPage();

  page.on("response", (res) => {
    const status = res.status();
    if (status >= 500) {
      telemetry.errorMessages.push({ status, url: res.url().split("?")[0] });
      telemetry.deadEnds.push({ type: "http_5xx", url: res.url().split("?")[0], status });
    }
    if (status === 404 && res.request().resourceType() === "document") {
      telemetry.deadEnds.push({ type: "http_404", url: res.url().split("?")[0] });
    }
  });

  try {
    await page.goto(persona.start, { waitUntil: "domcontentloaded", timeout: 60000 });
    telemetry.navigationTransitions.push(page.url());
    telemetry.pageSnapshots.push(await pageSignals(page));

    if (name === "anonymous") {
      const searchProbe = await tryFillSearchIfPresent(page, telemetry);
      telemetry.findings.push(searchProbe);
      // Prefer visible trip-type controls comprehension
      const tripTypes = await page.getByRole("button", { name: /one way|return|multi/i }).allTextContents().catch(() => []);
      const radios = await page.locator("label").filter({ hasText: /one way|return|multi-?city/i }).allTextContents().catch(() => []);
      telemetry.findings.push({ tripTypeLabels: [...tripTypes, ...radios].slice(0, 10) });
    }

    const visited = new Set();
    for (let i = 0; i < persona.maxActions; i++) {
      const signals = await pageSignals(page);
      telemetry.pageSnapshots.push(signals);
      if (signals.statusCodeHint > 0) {
        telemetry.errorMessages.push({ pageHint: signals.headings, url: signals.url });
      }
      if (/403|unauthorized|forbidden/i.test(signals.snippet)) {
        telemetry.unexpectedPermissions.push({ url: signals.url, snippet: signals.snippet.slice(0, 200) });
      }
      if (/coming soon|placeholder|lorem ipsum|under construction/i.test(signals.snippet)) {
        telemetry.confusingLabels.push({ url: signals.url, note: "placeholder_residue" });
      }
      if (/parwaaz|yoursdomain|yd travel|haseeb-master/i.test(signals.snippet)) {
        telemetry.findings.push({ severityHint: "P0", note: "legacy_brand_leak", url: signals.url });
      }

      const progressed = await clickBest(page, persona, visited, telemetry);
      telemetry.browserActions += 1;
      if (!progressed) {
        // try browser back once
        if (telemetry.navigationTransitions.length > 1 && telemetry.backtracks < 3) {
          await page.goBack({ waitUntil: "domcontentloaded" }).catch(() => {});
          telemetry.backtracks += 1;
          telemetry.actions.push({ type: "back" });
          continue;
        }
        break;
      }
    }

    // Heuristic success signals (black-box)
    const joined = telemetry.pageSnapshots.map((s) => `${s.title} ${s.snippet}`).join(" ").toLowerCase();
    if (name === "anonymous") {
      const hasSearchUi = /search|flight|from|destination|depart/i.test(joined);
      const formVisible = telemetry.findings.some((f) => f && f.formVisible);
      telemetry.success = hasSearchUi || formVisible;
      telemetry.finalUserConfidence = telemetry.success ? "medium" : "low";
      if (!telemetry.success) {
        telemetry.hiddenKnowledgeRequired = true;
        telemetry.findings.push({ severityHint: "P1", note: "search_not_discoverable" });
      }
    } else if (name === "customer") {
      telemetry.success = /support|help|ticket|request/i.test(joined);
      telemetry.finalUserConfidence = telemetry.success ? "medium" : "low";
    } else if (name === "agent") {
      telemetry.success = /booking|deposit|wallet|request|agent/i.test(joined);
      telemetry.finalUserConfidence = telemetry.success ? "medium" : "low";
    } else if (name === "staff" || name === "admin") {
      telemetry.success = /support|operations|inbox|assign|booking|ticket/i.test(joined);
      telemetry.finalUserConfidence = telemetry.success ? "medium" : "low";
    } else {
      telemetry.success = telemetry.deadEnds.filter((d) => d.type === "http_5xx").length === 0;
      telemetry.finalUserConfidence = "medium";
    }
  } finally {
    telemetry.elapsedSeconds = Math.round((Date.now() - started) / 1000);
    const outPath = path.join(outDir, `telemetry-${name}-${Date.now()}.json`);
    fs.writeFileSync(outPath, JSON.stringify(telemetry, null, 2));
    console.log(`TELEMETRY_PATH=${outPath}`);
    console.log(`PERSONA=${name}`);
    console.log(`SUCCESS=${telemetry.success ? "yes" : "no"}`);
    console.log(`ACTIONS=${telemetry.browserActions}`);
    console.log(`DEAD_ENDS=${telemetry.deadEnds.length}`);
    console.log(`ELAPSED_S=${telemetry.elapsedSeconds}`);
    await browser.close();
  }

  return telemetry;
}

const personaName = (process.argv[2] || "anonymous").toLowerCase();
await runPersona(personaName);
