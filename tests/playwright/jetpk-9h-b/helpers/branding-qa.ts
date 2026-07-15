import { expect, type Page } from '@playwright/test';
import { execSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

const auditDir = path.join('storage', 'app', 'audits', 'jetpk-9h-b');
const matrixPath = path.join(auditDir, 'branding-consumption-matrix.jsonl');

export type BrandingMatrixRow = {
  consumer: string;
  project: string;
  route: string;
  logoVisible: boolean;
  wordmarkHidden: boolean;
  logoStatus: number | null;
  aspectRatioOk: boolean;
  brokenImage: boolean;
  pass: boolean;
};

export function recordBrandingRow(row: BrandingMatrixRow): void {
  fs.mkdirSync(auditDir, { recursive: true });
  fs.appendFileSync(matrixPath, `${JSON.stringify(row)}\n`);
}

export async function assertLogoConsumer(
  page: Page,
  opts: {
    consumer: string;
    project: string;
    route: string;
    imgSelector: string;
    wordmarkSelector?: string;
    openDrawer?: boolean;
  },
): Promise<void> {
  await page.goto(opts.route, { waitUntil: 'domcontentloaded', timeout: 60_000 });

  if (opts.openDrawer) {
    const toggle = page.locator('#openDrawer, [data-jp-drawer-toggle], .jp-nav-toggle, button[aria-label*="menu" i]').first();
    if (await toggle.isVisible().catch(() => false)) {
      await toggle.click();
      await page.locator('.jp-drawer, [data-jp-drawer]').first().waitFor({ state: 'visible', timeout: 10_000 }).catch(() => {});
    }
  }

  const img = page.locator(opts.imgSelector).first();
  await expect(img).toBeVisible({ timeout: 15_000 });

  const src = await img.getAttribute('src');
  expect(src, `${opts.consumer} logo src`).toBeTruthy();

  const logoStatus = src ? (await page.request.get(src.startsWith('http') ? src : new URL(src, page.url()).toString())).status() : null;
  expect(logoStatus, `${opts.consumer} logo HTTP`).toBe(200);

  const metrics = await img.evaluate((el) => {
    const image = el as HTMLImageElement;
    return {
      naturalWidth: image.naturalWidth,
      naturalHeight: image.naturalHeight,
      clientWidth: image.clientWidth,
      clientHeight: image.clientHeight,
    };
  });

  const brokenImage = metrics.naturalWidth === 0 && metrics.naturalHeight === 0 && !(src ?? '').toLowerCase().includes('.svg');
  expect(brokenImage, `${opts.consumer} broken image`).toBe(false);

  const aspectRatioOk =
    metrics.naturalWidth > 0 &&
    metrics.naturalHeight > 0 &&
    metrics.clientWidth > 0 &&
    metrics.clientHeight > 0;
  expect(aspectRatioOk, `${opts.consumer} aspect ratio`).toBe(true);

  const wordmarkSelector = opts.wordmarkSelector ?? '.logo__wordmark';
  const wordmarkCount = await page.locator(wordmarkSelector).count();
  expect(wordmarkCount, `${opts.consumer} hardcoded wordmark`).toBe(0);

  const bodyText = await page.locator('body').innerText();
  for (const leak of ['Parwaaz', 'YD Travel', 'haseeb-master']) {
    expect(bodyText.includes(leak), `${opts.consumer} forbidden leak ${leak}`).toBe(false);
  }

  recordBrandingRow({
    consumer: opts.consumer,
    project: opts.project,
    route: opts.route,
    logoVisible: true,
    wordmarkHidden: wordmarkCount === 0,
    logoStatus,
    aspectRatioOk,
    brokenImage,
    pass: true,
  });
}

export async function assertApprovedFallback(page: Page, project: string): Promise<void> {
  await page.goto('/', { waitUntil: 'domcontentloaded' });
  const configuredAgencyLogo = page.locator('header .logo__img[src*="agencies/"]');
  await expect(configuredAgencyLogo).toHaveCount(0);

  const wordmark = page.locator('header .logo__wordmark');
  const fallbackLogo = page.locator('header .logo__img[src*="client-assets/jetpk/logo"]');
  const wordmarkVisible = await wordmark.isVisible().catch(() => false);
  const fallbackVisible = await fallbackLogo.isVisible().catch(() => false);
  expect(wordmarkVisible || fallbackVisible, 'approved JetPK fallback').toBe(true);

  recordBrandingRow({
    consumer: 'Public header fallback',
    project,
    route: '/',
    logoVisible: fallbackVisible,
    wordmarkHidden: !wordmarkVisible,
    logoStatus: null,
    aspectRatioOk: true,
    brokenImage: false,
    pass: true,
  });
}

export function clearBrandingLogoFixture(): void {
  execSync('php artisan jetpk:playwright-fixtures --clear-branding-logo', {
    cwd: process.cwd(),
    stdio: 'pipe',
  });
}

export function restoreBrandingLogoFixture(): void {
  execSync('php artisan jetpk:playwright-fixtures --restore-branding-logo', {
    cwd: process.cwd(),
    stdio: 'pipe',
  });
}
