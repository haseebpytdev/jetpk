import { expect, type Page } from "@playwright/test";
import fs from "node:fs";
import path from "node:path";

export const BASE = process.env.JP_CANARY_UAT_BASE ?? "https://jetpakistan.pk";
export const evidenceDir = path.resolve(__dirname, "../../../docs/evidence/jp-ai-production-canary-01");

export type CaseResult = {
  case_id: string;
  input: string[];
  visible_ai_response: string;
  http_status?: number;
  pass: boolean;
  notes?: string;
};

const reportPath = path.join(evidenceDir, "browser-matrix-report.jsonl");

export function appendCaseResult(result: CaseResult) {
  fs.mkdirSync(evidenceDir, { recursive: true });
  fs.appendFileSync(reportPath, JSON.stringify(result) + "\n", "utf8");
}

export async function openAskPanel(page: Page) {
  await page.goto(`${BASE}/`, { waitUntil: "networkidle", timeout: 120_000 });
  const fab = page.getByTestId("ask-jetpakistan-fab");
  await fab.scrollIntoViewIfNeeded();
  await expect(fab).toBeVisible({ timeout: 60_000 });
  await fab.click({ timeout: 30_000 });
  await expect(page.getByTestId("ask-jetpakistan-panel")).toBeVisible({ timeout: 30_000 });
}

export async function clearConversation(page: Page) {
  const panel = page.getByTestId("ask-jetpakistan-panel");
  if ((await panel.count()) === 0) {
    await openAskPanel(page);
  }
  await page.getByRole("button", { name: "Chat options" }).click();
  await page.getByRole("menuitem", { name: "Clear conversation" }).click();
  await page.waitForTimeout(1500);
}

export async function sendMessage(page: Page, text: string): Promise<{ status: number; body: string }> {
  const input = page.locator('[data-testid="ask-jetpakistan-panel"] input[type="text"], [data-testid="ask-jetpakistan-panel"] input').first();
  await input.fill(text);
  const responsePromise = page.waitForResponse(
    (res) => res.url().includes("/api/public/ai/chat") && res.request().method() === "POST",
    { timeout: 90_000 },
  );
  await page.getByRole("button", { name: /send/i }).click();
  const response = await responsePromise;
  await page.waitForTimeout(800);
  const messages = await page.getByTestId("ask-jetpakistan-messages").innerText();
  return { status: response.status(), body: messages };
}

export async function captureCase(
  page: Page,
  caseId: string,
  inputs: string[],
  assertFn: (text: string, lastStatus: number) => void,
) {
  fs.mkdirSync(evidenceDir, { recursive: true });
  let lastStatus = 0;
  let visible = "";

  try {
    await clearConversation(page);
    for (const msg of inputs) {
      const result = await sendMessage(page, msg);
      lastStatus = result.status;
      visible = result.body;
    }
    assertFn(visible, lastStatus);
    await page.screenshot({ path: path.join(evidenceDir, `${caseId}.png`), fullPage: false });
    appendCaseResult({
      case_id: caseId,
      input: inputs,
      visible_ai_response: visible.slice(-500),
      http_status: lastStatus,
      pass: true,
    });
  } catch (error) {
    await page.screenshot({ path: path.join(evidenceDir, `${caseId}-fail.png`), fullPage: false }).catch(() => {});
    appendCaseResult({
      case_id: caseId,
      input: inputs,
      visible_ai_response: visible.slice(-500),
      http_status: lastStatus,
      pass: false,
      notes: error instanceof Error ? error.message : String(error),
    });
    throw error;
  }
}

export function assertNonEmpty(text: string) {
  expect(text.trim().length).toBeGreaterThan(0);
}

export function assertPattern(text: string, pattern: RegExp) {
  expect(text).toMatch(pattern);
}
