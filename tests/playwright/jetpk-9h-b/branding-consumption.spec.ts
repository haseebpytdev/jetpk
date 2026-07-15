import { test, expect } from '@playwright/test';
import {
  assertApprovedFallback,
  assertLogoConsumer,
  clearBrandingLogoFixture,
  restoreBrandingLogoFixture,
} from './helpers/branding-qa';

const authDir = 'storage/app/playwright/jetpk-9h-b/auth';

test.describe('branding consumption with configured logo', () => {
  test('public header', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name.includes('consumption') || testInfo.project.name.includes('fallback'), 'public project only');
    await assertLogoConsumer(page, {
      consumer: 'Public header',
      project: testInfo.project.name,
      route: '/',
      imgSelector: 'header .logo__img',
    });
  });

  test('public footer', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name.includes('consumption') || testInfo.project.name.includes('fallback'), 'public project only');
    await assertLogoConsumer(page, {
      consumer: 'Public footer',
      project: testInfo.project.name,
      route: '/',
      imgSelector: 'footer .logo__img',
    });
  });

  test('mobile drawer', async ({ page }, testInfo) => {
    test.skip(!testInfo.project.name.includes('mobile'), 'mobile drawer project only');
    await assertLogoConsumer(page, {
      consumer: 'Mobile drawer',
      project: testInfo.project.name,
      route: '/',
      imgSelector: '#drawer .logo__img, .drawer .logo__img',
      openDrawer: true,
    });
  });

  test('auth layout', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name.includes('consumption') || testInfo.project.name.includes('fallback'), 'public project only');
    await assertLogoConsumer(page, {
      consumer: 'Auth layout',
      project: testInfo.project.name,
      route: '/login',
      imgSelector: '.jp-auth-brand__logo',
      wordmarkSelector: '.jp-auth-brand .logo__wordmark',
    });
  });

  test('public error page', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name.includes('consumption') || testInfo.project.name.includes('fallback'), 'public project only');
    await page.goto('/this-route-does-not-exist-jetpk-9hb', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('header').first()).toBeVisible();
    const img = page.locator('header .logo__img').first();
    if (await img.isVisible().catch(() => false)) {
      await assertLogoConsumer(page, {
        consumer: 'Public error page',
        project: testInfo.project.name,
        route: page.url(),
        imgSelector: 'header .logo__img',
      });
    }
  });

  test('favicon returns 200', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name.includes('consumption') || testInfo.project.name.includes('fallback'), 'public project only');
    await page.goto('/');
    const faviconHref = await page.locator('link[rel="icon"], link[rel="shortcut icon"]').first().getAttribute('href');
    expect(faviconHref).toBeTruthy();
    const faviconUrl = faviconHref!.startsWith('http') ? faviconHref! : new URL(faviconHref!, page.url()).toString();
    const status = (await page.request.get(faviconUrl)).status();
    expect(status).toBe(200);
  });

  test('admin dashboard sidebar', async ({ page }, testInfo) => {
    test.skip(!testInfo.project.name.includes('consumption'), 'admin auth project only');
    await assertLogoConsumer(page, {
      consumer: 'Dashboard sidebar (admin)',
      project: testInfo.project.name,
      route: '/admin',
      imgSelector: '.jp-side2__brand-logo',
      wordmarkSelector: 'header .logo__wordmark',
    });
  });

  test('page settings preview branding', async ({ page }, testInfo) => {
    test.skip(!testInfo.project.name.includes('consumption'), 'admin auth project only');
    await page.goto('/admin/page-settings/home', { waitUntil: 'domcontentloaded' });
    await page.locator('.jp-page-editor__preview, [data-jp-page-editor]').first().waitFor({ state: 'visible', timeout: 20_000 });
    const previewFrame = page.frameLocator('.jp-page-editor__preview iframe, [data-jp-preview-frame] iframe').first();
    const previewLogo = previewFrame.locator('header .logo__img').first();
    if (await previewLogo.count()) {
      await expect(previewLogo).toBeVisible();
    } else {
      await assertLogoConsumer(page, {
        consumer: 'Page Settings shell',
        project: testInfo.project.name,
        route: '/admin/page-settings/home',
        imgSelector: '.jp-side2__brand-logo',
      });
    }
  });

  test('booking safe page branding', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name.includes('consumption') || testInfo.project.name.includes('fallback'), 'public project only');
    await page.goto('/flights', { waitUntil: 'domcontentloaded' });
    await page.locator('header .logo__img').first().waitFor({ state: 'visible', timeout: 15_000 });
    await assertLogoConsumer(page, {
      consumer: 'Booking/flights safe page',
      project: testInfo.project.name,
      route: '/flights',
      imgSelector: 'header .logo__img',
    });
  });

  test('email template preview branding', async ({ page }, testInfo) => {
    test.skip(!testInfo.project.name.includes('consumption'), 'admin auth project only');
    const response = await page.goto('/admin/settings/communications/templates/preview/auth-email-verification', {
      waitUntil: 'domcontentloaded',
    });
    const status = response?.status() ?? 0;
    test.skip(status === 404, 'auth-email-verification preview unavailable locally');
    expect([200, 302]).toContain(status);
    const logo = page.locator('img[alt*="logo" i], .email-brand img, header img').first();
    if (await logo.isVisible().catch(() => false)) {
      const src = await logo.getAttribute('src');
      expect(src).toBeTruthy();
      const logoStatus = (await page.request.get(new URL(src!, page.url()).toString())).status();
      expect(logoStatus).toBe(200);
    }
  });
});

test.describe('role shells with configured logo', () => {
  const roleShells = [
    { role: 'staff', route: '/staff', selector: '.jp-side2__brand-logo' },
    { role: 'agent', route: '/agent', selector: '.jp-portal__logo-img' },
    { role: 'agent-staff', route: '/agent', selector: '.jp-portal__logo-img' },
    { role: 'customer', route: '/customer', selector: '.jp-portal__logo-img' },
  ] as const;

  for (const shell of roleShells) {
    test(`${shell.role} shell`, async ({ browser }, testInfo) => {
      test.skip(!testInfo.project.name.includes('consumption'), 'branding consumption project only');
      const authPath = `${authDir}/${shell.role}.json`;
      const context = await browser.newContext({
        storageState: authPath,
        viewport: { width: 1440, height: 900 },
      });
      const page = await context.newPage();
      try {
        await assertLogoConsumer(page, {
          consumer: `${shell.role} shell`,
          project: testInfo.project.name,
          route: shell.route,
          imgSelector: shell.selector,
        });
      } finally {
        await context.close();
      }
    });
  }
});

test.describe('fallback when logo cleared', () => {
  test.beforeAll(() => {
    clearBrandingLogoFixture();
  });

  test.afterAll(() => {
    restoreBrandingLogoFixture();
  });

  test('restores approved JetPK fallback only', async ({ page }, testInfo) => {
    await assertApprovedFallback(page, testInfo.project.name);
  });
});
