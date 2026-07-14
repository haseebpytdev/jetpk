import fs from 'node:fs';
import path from 'node:path';
import type { Page } from '@playwright/test';
import { SCREENSHOTS_DIR } from './constants';
import type { AuditState, ScreenshotEntry } from './audit-state';
import { getAuditState, persistAuditState } from './audit-state';

export function screenshotPath(name: string, viewport: string): string {
  const safe = name.replace(/[^a-z0-9._-]+/gi, '-').toLowerCase();
  return path.join(SCREENSHOTS_DIR, `${viewport}__${safe}.png`);
}

export async function captureScreenshot(
  page: Page,
  name: string,
  viewport: string,
): Promise<ScreenshotEntry> {
  const rel = screenshotPath(name, viewport);
  const abs = path.join(process.cwd(), rel);
  fs.mkdirSync(path.dirname(abs), { recursive: true });
  await page.screenshot({ path: abs, fullPage: false });

  const entry: ScreenshotEntry = {
    name,
    path: rel.replace(/\\/g, '/'),
    viewport,
    url: page.url(),
  };

  const state = getAuditState();
  state.screenshots.push(entry);
  persistAuditState();

  return entry;
}
