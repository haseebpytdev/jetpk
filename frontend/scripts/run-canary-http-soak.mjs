/**
 * JP-AI-PRODUCTION-CANARY-HTTP-GATEWAY-01 — HTTP path soak via real Laravel chat API.
 */
import { chromium } from "playwright";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { loadQaPasswordFromVault } from "../../dashboard/scripts/jp-dash-03-acceptance/credential-vault.mjs";
import {
  BASE,
  evidenceDir,
  fetchCsrfToken,
  getCanaryFaultToken,
  postLogin,
} from "./canary-matrix-helpers.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ADMIN_EMAIL = "jp-dash-03-qa-admin@jetpakistan.pk";
const TOTAL = Number(process.env.JP_CANARY_HTTP_SOAK_TOTAL ?? 50);
const outPath = path.join(
  path.resolve(__dirname, "../../docs/evidence/jp-ai-production-canary-http-gateway-01"),
  "http-soak-report.json",
);

const PROMPTS = [
  "LHE to DXB tomorrow",
  "one way LHE to DXB 20 Dec 1 adult",
  "baggage allowance",
  "lahore se dubai kal",
  "what is carry-on policy",
];

async function postChat(request, csrf, message, headers = {}) {
  const response = await request.post(`${BASE}/laravel/api/public/ai/chat`, {
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      "X-Requested-With": "XMLHttpRequest",
      "X-XSRF-TOKEN": csrf,
      ...headers,
    },
    data: { message },
  });
  let body = {};
  try {
    body = await response.json();
  } catch {
    body = {};
  }
  return { status: response.status(), body };
}

function classify(body, status) {
  const message = String(body.message ?? "");
  if (status === 429 || body.status === "rate_limited") return "AI_UNAVAILABLE";
  if (/temporarily unavailable|try again shortly/i.test(message)) return "AI_UNAVAILABLE";
  if (/127\.0\.0\.1:1(?:\/|$|\s|"|')/.test(JSON.stringify(body))) return "WRONG_GATEWAY_URL";
  if (!message.trim()) return "EMPTY_RESPONSE";
  if (/connection refused|ECONNREFUSED/i.test(message)) return "CONNECTION_REFUSED";
  return "SUCCESS";
}

async function main() {
  const password = loadQaPasswordFromVault("admin");
  const faultToken = getCanaryFaultToken();
  if (!password) {
    console.error("MISSING_ADMIN_QA_PASSWORD");
    process.exit(2);
  }
  if (!faultToken) {
    console.error("MISSING_CANARY_FAULT_TOKEN");
    process.exit(2);
  }

  fs.mkdirSync(path.dirname(outPath), { recursive: true });
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext();
  const page = await context.newPage();

  await page.goto(`${BASE}/login`, { waitUntil: "domcontentloaded", timeout: 120_000 });
  const login = await postLogin(page, ADMIN_EMAIL, password);
  if (!login.ok()) {
    throw new Error(`login failed HTTP ${login.status()}`);
  }

  let csrf = await fetchCsrfToken(page);
  const health = await page.request.get(`${BASE}/laravel/api/public/ai/health`, {
    headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
  });
  const healthBody = await health.json();
  const gateway = healthBody.ai_lab_gateway ?? null;

  const counts = {
    TOTAL,
    SUCCESS: 0,
    AI_UNAVAILABLE: 0,
    CONNECTION_REFUSED: 0,
    WRONG_GATEWAY_URL: 0,
    EMPTY_RESPONSE: 0,
  };
  const rows = [];

  for (let i = 0; i < TOTAL; i += 1) {
    const prompt = PROMPTS[i % PROMPTS.length];
    const result = await postChat(page.request, csrf, prompt);
    const bucket = classify(result.body, result.status);
    counts[bucket] = (counts[bucket] ?? 0) + 1;
    rows.push({ index: i + 1, prompt, status: result.status, bucket, response_status: result.body.status ?? null });
    if (result.status === 419) {
      csrf = await fetchCsrfToken(page);
    }
    if (i > 0 && i % 8 === 0) {
      await page.waitForTimeout(65_000);
    } else {
      await page.waitForTimeout(Number(process.env.JP_CANARY_HTTP_SOAK_PACE_MS ?? 8000));
    }
  }

  const faultRecovery = {};
  for (const [label, mode] of [
    ["GATEWAY_DOWN_THEN_NORMAL", "SIMULATE_GATEWAY_DOWN"],
    ["OLLAMA_DOWN_THEN_NORMAL", "SIMULATE_OLLAMA_DOWN"],
    ["MALFORMED_THEN_NORMAL", "SIMULATE_MALFORMED_RESPONSE"],
  ]) {
    const fault = await postChat(page.request, csrf, "LHE to DXB tomorrow", {
      "X-JP-AI-Canary-Fault-Mode": mode,
      "X-JP-AI-Canary-Fault-Token": faultToken,
    });
    const faultBucket = classify(fault.body, fault.status);
    const normal = await postChat(page.request, csrf, "LHE to DXB tomorrow");
    const normalBucket = classify(normal.body, normal.status);
    faultRecovery[label] = {
      fault_bucket: faultBucket,
      normal_bucket: normalBucket,
      recovered: normalBucket === "SUCCESS",
    };
    await page.waitForTimeout(1500);
  }

  await browser.close();

  const report = {
    phase: "JP-AI-PRODUCTION-CANARY-HTTP-GATEWAY-01",
    started_at: new Date().toISOString(),
    health_gateway: gateway,
    counts,
    fault_recovery: faultRecovery,
    rows,
    pass:
      counts.SUCCESS === TOTAL &&
      counts.AI_UNAVAILABLE === 0 &&
      counts.CONNECTION_REFUSED === 0 &&
      counts.WRONG_GATEWAY_URL === 0 &&
      counts.EMPTY_RESPONSE === 0 &&
      Object.values(faultRecovery).every((entry) => entry.recovered === true),
  };
  fs.writeFileSync(outPath, JSON.stringify(report, null, 2));
  console.log(JSON.stringify(report, null, 2));
  process.exit(report.pass ? 0 : 1);
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
