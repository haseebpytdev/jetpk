import { expect, type Page } from "@playwright/test";
import { mkdirSync, writeFileSync } from "node:fs";
import path from "node:path";
import { themeStorageValue, type ThemeMode } from "./jp-ui-02-scenarios";

export const AUDIT_ROOT = path.resolve(process.cwd(), ".visual-audit", "jp-ui-03a");
export const MANIFEST_PATH = path.join(AUDIT_ROOT, "capture-manifest.json");
export const SUMMARY_PATH = path.join(AUDIT_ROOT, "capture-summary.json");

export type CaptureRecord = {
  id: string;
  family: string;
  route: string;
  theme: ThemeMode;
  resolvedTheme: string;
  colorScheme: "light" | "dark";
  viewport: string;
  width: number;
  height: number;
  zoom: number;
  state: string;
  screenshot: string;
  overflowOk: boolean;
  hydrationWarnings: string[];
  pageErrors: string[];
  timestamp: string;
  result: "passed" | "failed";
};

export const captureRecords: CaptureRecord[] = [];

export function writeManifest(): void {
  mkdirSync(AUDIT_ROOT, { recursive: true });
  const passed = captureRecords.filter((r) => r.result === "passed").length;
  const failed = captureRecords.filter((r) => r.result === "failed").length;
  writeFileSync(
    MANIFEST_PATH,
    JSON.stringify(
      {
        generatedAt: new Date().toISOString(),
        expectedScenarioCount: Number(process.env.JP_UI_03A_EXPECTED_COUNT ?? "0"),
        captureCount: captureRecords.length,
        passed,
        failed,
        skipped: 0,
        captures: captureRecords,
      },
      null,
      2,
    ),
    "utf8",
  );
  writeFileSync(
    SUMMARY_PATH,
    JSON.stringify(
      {
        generatedAt: new Date().toISOString(),
        captureCount: captureRecords.length,
        passed,
        failed,
        manifestPath: MANIFEST_PATH,
      },
      null,
      2,
    ),
    "utf8",
  );
}

export async function applyTheme(page: Page, theme: ThemeMode): Promise<{ preference: string; colorScheme: "light" | "dark" }> {
  const { preference, emulateDark } = themeStorageValue(theme);
  await page.emulateMedia({ colorScheme: emulateDark ? "dark" : "light" });
  await page.addInitScript((pref) => {
    localStorage.setItem("jp-theme-preference", pref);
  }, preference);
  return { preference, colorScheme: emulateDark ? "dark" : "light" };
}

export async function assertResolvedTheme(page: Page, theme: ThemeMode): Promise<string> {
  const expected = theme === "dark" || theme === "system-dark" ? "dark" : "light";
  await expect(page.locator("html")).toHaveAttribute("data-theme", expected);
  return expected;
}

export async function assertNoHorizontalOverflow(page: Page): Promise<boolean> {
  const overflow = await page.evaluate(() => {
    const doc = document.documentElement;
    return doc.scrollWidth - doc.clientWidth;
  });
  expect(overflow).toBeLessThanOrEqual(1);
  return overflow <= 1;
}

export function attachPageMonitors(page: Page): { hydrationWarnings: string[]; pageErrors: string[] } {
  const hydrationWarnings: string[] = [];
  const pageErrors: string[] = [];
  page.on("console", (msg) => {
    if (msg.type() === "error" && /hydration/i.test(msg.text())) {
      hydrationWarnings.push(msg.text());
    }
  });
  page.on("pageerror", (error) => {
    pageErrors.push(error.message);
  });
  return { hydrationWarnings, pageErrors };
}

export async function captureScenario(
  page: Page,
  record: Omit<CaptureRecord, "timestamp" | "overflowOk" | "hydrationWarnings" | "pageErrors" | "result" | "resolvedTheme" | "colorScheme"> & {
    resolvedTheme: string;
    colorScheme: "light" | "dark";
    monitors: { hydrationWarnings: string[]; pageErrors: string[] };
    fullPage?: boolean;
  },
): Promise<void> {
  const screenshotPath = path.join(AUDIT_ROOT, record.screenshot);
  mkdirSync(AUDIT_ROOT, { recursive: true });
  await page.screenshot({ path: screenshotPath, fullPage: record.fullPage ?? true });

  const overflowOk = await assertNoHorizontalOverflow(page);
  expect(record.monitors.hydrationWarnings).toEqual([]);
  expect(record.monitors.pageErrors).toEqual([]);

  captureRecords.push({
    ...record,
    overflowOk,
    hydrationWarnings: [...record.monitors.hydrationWarnings],
    pageErrors: [...record.monitors.pageErrors],
    timestamp: new Date().toISOString(),
    result: overflowOk && record.monitors.hydrationWarnings.length === 0 && record.monitors.pageErrors.length === 0 ? "passed" : "failed",
  });
}

export async function applyZoom(page: Page, zoom: number): Promise<void> {
  if (zoom === 1) return;
  await page.evaluate((factor) => {
    document.documentElement.style.zoom = String(factor);
  }, zoom);
}
