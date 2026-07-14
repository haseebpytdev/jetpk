import { test, expect, type Page } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

const screenshotDir = path.join('tests', 'playwright', 'artifacts', 'jetpk-9h-c', 'screenshots');
const auditDir = path.join('storage', 'app', 'audits', 'jetpk-9h-c');

function shotName(project: string, label: string): string {
  return path.join(screenshotDir, `${project}-${label}.png`);
}

async function assertNoHorizontalOverflow(page: Page): Promise<void> {
  const overflow = await page.evaluate(() => {
    const doc = document.documentElement;
    return doc.scrollWidth > doc.clientWidth + 2;
  });
  expect(overflow, 'horizontal overflow').toBe(false);
}

async function collectConsoleAndNetwork(page: Page): Promise<{ consoleErrors: string[]; failedRequests: string[] }> {
  const consoleErrors: string[] = [];
  const failedRequests: string[] = [];
  page.on('console', (msg) => {
    if (msg.type() === 'error') consoleErrors.push(msg.text());
  });
  page.on('response', (res) => {
    const url = res.url();
    if (res.status() >= 400 && url.startsWith(page.url().split('/').slice(0, 3).join('/'))) {
      failedRequests.push(`${res.status()} ${url}`);
    }
  });
  return { consoleErrors, failedRequests };
}

async function gotoBrandingAsAdmin(page: Page): Promise<void> {
  await page.goto('/admin/settings/branding', { waitUntil: 'domcontentloaded', timeout: 60_000 });
  if (!page.url().includes('/login')) {
    return;
  }

  const email = process.env.JETPK_PW_ADMIN_EMAIL || 'admin@ota.demo';
  const password = process.env.JETPK_PW_PASSWORD || 'password';
  const otp = process.env.OTP_DEMO_FIXED_CODE || '123456';

  await page.locator('form.jp-auth-form input[name="login"]').fill(email);
  await page.locator('form.jp-auth-form input[name="password"]').fill(password);
  await page.locator('form.jp-auth-form button[type="submit"]').click();

  const onOtp = await page.waitForURL(/\/login\/otp/, { timeout: 12_000 }).then(() => true).catch(() => false);
  if (onOtp) {
    await page.locator('input[name="otp"]').fill(otp);
    await page.locator('form button[type="submit"]').first().click({ noWaitAfter: true });
  }

  await Promise.race([
    page.waitForURL((url) => url.pathname.startsWith('/admin'), { timeout: 90_000 }),
    page.locator('#jp-dash-sidebar').first().waitFor({ state: 'visible', timeout: 90_000 }),
  ]);

  await page.goto('/admin/settings/branding', { waitUntil: 'domcontentloaded', timeout: 60_000 });
}

test.describe('public homepage hero visibility', () => {
  test('public homepage', async ({ page }, testInfo) => {
    const consoleErrors: string[] = [];
    const failedRequests: string[] = [];
    page.on('console', (msg) => {
      if (msg.type() === 'error') consoleErrors.push(msg.text());
    });
    page.on('response', (res) => {
      const url = res.url();
      if (res.status() >= 400 && url.startsWith(new URL(page.url() || 'http://127.0.0.1:8000').origin)) {
        failedRequests.push(`${res.status()} ${url}`);
      }
    });
    await page.goto('/', { waitUntil: 'domcontentloaded' });

    const hero = page.locator('section.hero');
    await expect(hero).toBeVisible();
    const hasImage = await hero.evaluate((el) => el.classList.contains('hero--has-image'));

    if (hasImage) {
      await expect(page.locator('section.hero .route-net')).toHaveCount(0);
      await expect(page.locator('section.hero .topo')).toHaveCount(0);
      await expect(page.locator('section.hero .globe-wrap')).toHaveCount(0);
      await expect(page.locator('section.hero .hero-arc')).toHaveCount(0);
      await expect(page.locator('section.hero .hero-readability')).toHaveCount(1);
    }

    await expect(page.locator('section.hero h1')).toBeVisible();
    await expect(page.locator('section.hero .search, section.hero .jp-master-search').first()).toBeVisible();

    const logo = page.locator('header .logo__img').first();
    if (await logo.isVisible().catch(() => false)) {
      const height = await logo.evaluate((el) => (el as HTMLImageElement).clientHeight);
      expect(height).toBeGreaterThanOrEqual(20);
      expect(height).toBeLessThanOrEqual(80);
    }

    await assertNoHorizontalOverflow(page);
    await page.screenshot({ path: shotName(testInfo.project.name, 'homepage'), fullPage: false });

    expect(consoleErrors, 'console errors').toEqual([]);
    expect(failedRequests, 'failed same-origin requests').toEqual([]);

    fs.mkdirSync(auditDir, { recursive: true });
    fs.writeFileSync(
      path.join(auditDir, `homepage-${testInfo.project.name}.json`),
      JSON.stringify({ hasImage, consoleErrors, failedRequests }, null, 2),
    );
  });
});

test.describe('branding page form contract', () => {
  test('branding page', async ({ page }, testInfo) => {
    await gotoBrandingAsAdmin(page);
    await page.locator('.jp-branding-page').waitFor({ state: 'visible', timeout: 30_000 });

    await expect(page.locator('[data-jp-branding-save]')).toBeVisible();
    await expect(page.locator('[data-jp-logo-size-slider]')).toBeVisible();

    const rawFileInputs = page.locator('.jp-branding-page input[type="file"]:not(.jp-file-control__input)');
    await expect(rawFileInputs).toHaveCount(0);

    const styledFileInputs = page.locator('.jp-branding-page .jp-file-control__input');
    expect(await styledFileInputs.count()).toBeGreaterThanOrEqual(3);

    const slider = page.locator('[data-jp-logo-size-slider]');
    const valueOut = page.locator('[data-jp-logo-size-value]');
    await slider.fill('52');
    await expect(valueOut).toHaveText('52px');

    const preview = page.locator('[data-jp-logo-size-preview]');
    if (await preview.isVisible().catch(() => false)) {
      const previewHeight = await preview.evaluate((el) => (el as HTMLImageElement).style.height);
      expect(previewHeight).toBe('52px');
    }

    await page.locator('[data-jp-logo-size-reset]').click();
    await expect(valueOut).toHaveText('36px');

    const overlaps = await page.evaluate(() => {
      const fields = Array.from(document.querySelectorAll('.jp-branding-page .jp-field'));
      for (let i = 0; i < fields.length; i++) {
        for (let j = i + 1; j < fields.length; j++) {
          const a = fields[i].getBoundingClientRect();
          const b = fields[j].getBoundingClientRect();
          const intersects = !(a.right < b.left || a.left > b.right || a.bottom < b.top || a.top > b.bottom);
          if (intersects && Math.abs(a.top - b.top) < 2 && Math.abs(a.left - b.left) < 2) {
            return true;
          }
        }
      }
      return false;
    });
    expect(overlaps, 'overlapping field wrappers').toBe(false);

    await assertNoHorizontalOverflow(page);

    const label = testInfo.project.name.includes('1440')
      ? 'branding-full'
      : testInfo.project.name.includes('768')
        ? 'branding-tablet'
        : testInfo.project.name.includes('390')
          ? 'branding-mobile'
          : 'branding-page';
    await page.screenshot({ path: shotName(testInfo.project.name, label), fullPage: true });

    if (testInfo.project.name.includes('1440')) {
      const mediaSection = page.locator('.jp-branding-media');
      await mediaSection.screenshot({ path: shotName(testInfo.project.name, 'branding-media') });
      const logoSection = page.locator('[data-jp-logo-size-control]');
      await logoSection.screenshot({ path: shotName(testInfo.project.name, 'branding-logo-size') });
    }

    if (testInfo.project.name === 'branding-desktop-1440') {
      await slider.fill('48');
      await page.locator('[data-jp-branding-save]').click();
      await page.waitForURL(/\/admin\/settings\/branding/, { timeout: 30_000 });
      await expect(page.locator('[data-jp-logo-size-slider]')).toHaveValue('48');

      await page.goto('/', { waitUntil: 'domcontentloaded' });
      const logo = page.locator('header .logo__img').first();
      if (await logo.isVisible().catch(() => false)) {
        const height = await logo.evaluate((el) => (el as HTMLImageElement).clientHeight);
        expect(Math.abs(height - 48)).toBeLessThanOrEqual(4);
      }
    }
  });
});
