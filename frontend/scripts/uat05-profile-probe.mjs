/**
 * UAT-05 Phase 1: boolean-only QA superadmin profile/contact probe (no PII output).
 */
import { chromium } from "playwright";
import { getStoragePath } from "../../dashboard/scripts/jp-dash-03-acceptance/auth-storage.mjs";
import {
  BASE,
  clearConversation,
  openAskPanel,
  waitForChatInputReady,
} from "./live-readonly-qa-helpers.mjs";
const browser = await chromium.launch({ headless: true });
const ctx = await browser.newContext({ storageState: getStoragePath("admin") });
const page = await ctx.newPage();

await openAskPanel(page);
await clearConversation(page);
await waitForChatInputReady(page, 180_000);

const payload = await page.evaluate(async (base) => {
  const csrfRes = await fetch(`${base}/laravel/api/public/content/csrf-token`, {
    credentials: "include",
    headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
  });
  let csrf = "";
  if (csrfRes.ok) {
    const csrfJson = await csrfRes.json().catch(() => ({}));
    csrf = csrfJson.token ?? "";
  }
  const res = await fetch(`${base}/laravel/api/public/ai/chat`, {
    method: "POST",
    credentials: "include",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      "X-Requested-With": "XMLHttpRequest",
      ...(csrf ? { "X-XSRF-TOKEN": csrf } : {}),
    },
    body: JSON.stringify({ message: "I need a flight Lahore to Jeddah" }),
  });
  return res.json().catch(() => ({}));
}, BASE);
const fields = payload?.lead_capture?.fields ?? [];
const known = payload?.lead_capture?.known_fields ?? [];

const report = {
  PROFILE_NAME_AVAILABLE: known.includes("name") || !fields.includes("name") ? "YES" : "NO",
  PROFILE_EMAIL_AVAILABLE: known.includes("email") || !fields.includes("email") ? "YES" : "NO",
  PROFILE_PHONE_AVAILABLE: known.includes("phone") || !fields.includes("phone") ? "YES" : "NO",
  PROFILE_CONTACT_REUSABLE:
    !fields.includes("name") && !fields.includes("email") && !fields.includes("phone") ? "YES" : "NO",
  LEAD_FIELDS_REQUESTED: fields,
  LEAD_KNOWN_FIELDS: known,
  CONSENT_REQUIRED: fields.includes("contact_consent") ? "YES" : "NO",
  LS12_BLOCKER_INITIAL: "LEAD_GATE",
  LS12_ROUTE_EXECUTED: "NO",
  AUTHENTICATED_LEAD_REUSE_DEFECT:
    fields.includes("name") && fields.includes("email") && fields.includes("phone") ? "YES" : "NO",
};

console.log(JSON.stringify(report, null, 2));
await browser.close();
process.exit(0);
