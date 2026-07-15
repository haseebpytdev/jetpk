import { test, expect, type Page } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { execSync } from 'node:child_process';

const opaqueLogo = path.join('tests', 'fixtures', 'branding', 'opaque-logo.png');

async function gotoBranding(page: Page): Promise<void> {
  await page.goto('/admin/settings/branding', { waitUntil: 'domcontentloaded', timeout: 60_000 });
  await page.locator('[data-jp-logo-background]').waitFor({ state: 'visible', timeout: 30_000 });
}

test.describe('branding background removal workflow', () => {
  test.beforeAll(() => {
    if (!fs.existsSync(opaqueLogo)) {
      execSync('php scripts/generate-opaque-logo-fixture.php', { cwd: process.cwd(), stdio: 'inherit' });
    }
  });

  test('opaque logo staging, fixture processing, accept, and consumption', async ({ page }, testInfo) => {
    await gotoBranding(page);

    await page.locator('[data-jp-logo-file]').setInputFiles(opaqueLogo);
    await page.locator('[data-jp-logo-bg-toggle]').check();
    await page.locator('[data-jp-logo-bg-process]').click();

    await expect(page.locator('[data-jp-logo-bg-status]')).toContainText(/processed|accepted|review/i, { timeout: 90_000 });
    await expect(page.locator('[data-jp-logo-bg-processed-preview]')).toBeVisible({ timeout: 30_000 });
    await expect(page.locator('[data-jp-logo-bg-original-preview]')).toBeVisible();

    for (const sel of ['[data-jp-logo-bg-processed-preview]', '[data-jp-logo-bg-processed-white]', '[data-jp-logo-bg-processed-dark]']) {
      const img = page.locator(sel);
      await expect(img).toBeVisible();
      const box = await img.boundingBox();
      expect(box?.width ?? 0).toBeGreaterThan(8);
    }

    await page.locator('[data-jp-logo-bg-accept]').click();
    await page.waitForLoadState('domcontentloaded');
    await page.reload({ waitUntil: 'domcontentloaded' });

    const headerLogo = page.locator('header .logo__img, .jp-side2__brand img').first();
    await page.goto('/', { waitUntil: 'domcontentloaded' });
    await expect(headerLogo).toBeVisible({ timeout: 20_000 });
    const src = await headerLogo.getAttribute('src');
    expect(src).toBeTruthy();
    const status = await page.request.get(new URL(src!, page.url()).toString()).then((r) => r.status());
    expect(status).toBe(200);

    const audit = execSync(`php artisan jetpk:image-transparency-audit "${path.join('tests', 'fixtures', 'branding', 'transparent-logo.png')}"`, {
      cwd: process.cwd(),
      encoding: 'utf8',
    });
    expect(audit).toContain('has_transparent_pixels=yes');

    await page.screenshot({
      path: path.join('tests', 'playwright', 'artifacts', 'jetpk-9h-c2', 'screenshots', `${testInfo.project.name}-bg-removal.png`),
      fullPage: true,
    });
  });
});
