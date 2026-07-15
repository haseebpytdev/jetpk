import fs from 'node:fs';
import path from 'node:path';
import type { Page } from '@playwright/test';
import type { ViewportName } from './viewports';

export function uiTestRoot(): string {
  return path.join(process.cwd(), 'UI_test');
}

export function screenshotPath(
  roleDir: string,
  pageKey: string,
  browser: string,
  viewport: ViewportName,
): string {
  return path.join(uiTestRoot(), 'screenshots', roleDir, pageKey, `${browser}-${viewport}.png`);
}

export function interactiveScreenshotDir(roleDir: string, pageKey: string): string {
  return path.join(uiTestRoot(), 'screenshots', 'interactive', roleDir, pageKey);
}

export function failureScreenshotPath(
  roleDir: string,
  pageKey: string,
  browser: string,
  viewport: ViewportName,
  suffix: string,
): string {
  return path.join(uiTestRoot(), 'failures', roleDir, pageKey, `${browser}-${viewport}-${suffix}.png`);
}

export function ensureDirForFile(filePath: string): void {
  fs.mkdirSync(path.dirname(filePath), { recursive: true });
}

export async function captureFullPageScreenshot(
  page: Page,
  filePath: string,
): Promise<string | undefined> {
  try {
    ensureDirForFile(filePath);
    await page.screenshot({ path: filePath, fullPage: true });
    return filePath;
  } catch {
    return undefined;
  }
}

export function ensureAuditDirs(): void {
  const dirs = [
    'screenshots/public',
    'screenshots/customer',
    'screenshots/agent',
    'screenshots/agent_staff_full',
    'screenshots/agent_staff_restricted',
    'screenshots/admin',
    'screenshots/staff',
    'screenshots/interactive',
    'reports',
    'traces',
    'videos',
    'failures',
    'latest',
    '.auth',
  ];

  for (const dir of dirs) {
    fs.mkdirSync(path.join(uiTestRoot(), dir), { recursive: true });
  }
}
