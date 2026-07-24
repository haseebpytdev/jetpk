import { expect, test, type Page } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { getOverflowMetrics } from './helpers/layout-checks';
import { loginWithJetpkOtp } from './helpers/jetpk-login-with-otp';

const EVIDENCE_DIR = path.join('test-results', 'jetpk-canonical-ui-final');

const HOME_VIEWPORTS = [
  { name: 'mobile360', width: 360, height: 800 },
  { name: 'mobile390', width: 390, height: 844 },
  { name: 'mobile430', width: 430, height: 932 },
  { name: 'tablet768', width: 768, height: 1024 },
  { name: 'tabletLandscape1024', width: 1024, height: 768 },
  { name: 'laptop1366', width: 1366, height: 768 },
  { name: 'desktop1440', width: 1440, height: 900 },
  { name: 'desktop1920', width: 1920, height: 1080 },
] as const;

const AUTH_VIEWPORTS = [
  { name: 'mobile390', width: 390, height: 844 },
  { name: 'desktop1440', width: 1440, height: 900 },
] as const;

const FORBIDDEN_MARKERS = [
  'ota-mobile-app-shell',
  'jp-desktop-mobile-app-toggle',
  'ota-desktop-mobile-toggle',
  'ota-mobile-bottom-bar',
  'ota-mobile-home-trust-bar',
  'ota-mobile-auth',
  'Mobile App',
  'Parwaaz',
  'haseeb-master',
  'support@haseebasif.com',
  'YoursDomain',
  'YD Travel',
  'Asif Travels',
];

async function assertNoForbiddenMarkers(page: Page): Promise<void> {
  const html = await page.content();
  for (const marker of FORBIDDEN_MARKERS) {
    expect(html, `forbidden marker: ${marker}`).not.toContain(marker);
  }
}

async function assertNoHeroCtas(page: Page): Promise<void> {
  const hero = page.locator('.hero');
  await expect(hero).toBeVisible();
  await expect(hero.getByRole('link', { name: /^Search flights$/i })).toHaveCount(0);
  await expect(hero.getByRole('link', { name: /^Group fares$/i })).toHaveCount(0);
  await expect(hero.locator('.hero-ctas')).toHaveCount(0);
  await expect(hero.locator('a[href*="/group-ticketing"]')).toHaveCount(0);
}

async function capture(page: Page, name: string): Promise<void> {
  fs.mkdirSync(EVIDENCE_DIR, { recursive: true });
  await page.screenshot({ path: path.join(EVIDENCE_DIR, `${name}.png`), fullPage: true });
}

function isBenignConsoleError(message: string): boolean {
  return (
    message.includes('ERR_NAME_NOT_RESOLVED') ||
    message.includes('fonts.googleapis.com') ||
    message.includes('fonts.gstatic.com') ||
    message.includes('Failed to load resource: the server responded with a status of 404 ()')
  );
}

async function collectPageErrors(page: Page): Promise<string[]> {
  const errors: string[] = [];
  page.on('pageerror', (err) => errors.push(err.message));
  page.on('console', (msg) => {
    if (msg.type() === 'error' && !isBenignConsoleError(msg.text())) {
      errors.push(msg.text());
    }
  });
  return errors;
}

async function assertSameOriginAssetsHealthy(page: Page): Promise<void> {
  const base = new URL(page.url());
  const failed = await page.evaluate(() =>
    performance
      .getEntriesByType('resource')
      .filter((entry) => {
        const url = (entry as PerformanceResourceTiming).name;
        return (
          url.includes('/css/') ||
          url.includes('/js/') ||
          url.includes('/themes/frontend/jetpakistan/')
        );
      })
      .map((entry) => ({
        name: (entry as PerformanceResourceTiming).name,
        duration: (entry as PerformanceResourceTiming).duration,
      })),
  );
  const broken = failed.filter((entry) => entry.name.startsWith(base.origin) && entry.duration === 0);
  expect(broken, JSON.stringify(broken)).toHaveLength(0);
}

test.describe.configure({ mode: 'serial' });

test.describe('homepage canonical responsive', () => {
  for (const vp of HOME_VIEWPORTS) {
    test(`homepage @ ${vp.name}`, async ({ page }) => {
      await page.setViewportSize({ width: vp.width, height: vp.height });
      const errors: string[] = [];
      page.on('pageerror', (err) => errors.push(err.message));
      page.on('console', (msg) => {
        if (msg.type() === 'error' && !isBenignConsoleError(msg.text())) errors.push(msg.text());
      });

      const response = await page.goto('/', { waitUntil: 'domcontentloaded' });
      expect(response?.status()).toBe(200);
      await page.waitForLoadState('networkidle');

      await expect(page.locator('header, .jp-header').first()).toBeVisible();
      await expect(page.locator('.hero')).toBeVisible();
      await expect(page.locator('#jp-flight-search, [data-jp-search]').first()).toBeVisible();
      await expect(page.locator('.chips, .hero .chips').first()).toBeVisible();
      await expect(page.locator('footer, .jp-footer').first()).toBeVisible();

      const sectionMarkers = await page.locator('[class*="jp-section"], [id^="jp-section"]').count();
      const orderedSections = await page.locator('html').evaluate(() => {
        const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_COMMENT);
        const markers: string[] = [];
        while (walker.nextNode()) {
          const text = walker.currentNode.textContent ?? '';
          const match = text.match(/jp-section-start:([^:]+):order-(\d+)/);
          if (match) markers.push(`${match[1]}:${match[2]}`);
        }
        return markers;
      });
      expect(orderedSections.length, 'CMS section stack markers').toBeGreaterThan(0);

      await assertNoHeroCtas(page);
      await assertNoForbiddenMarkers(page);

      const overflow = await getOverflowMetrics(page);
      expect(overflow.hasOverflow, `overflow ${overflow.bodyScrollWidth} > ${overflow.innerWidth}`).toBe(false);
      expect(errors, errors.join('\n')).toHaveLength(0);
      await assertSameOriginAssetsHealthy(page);

      if (['mobile390', 'mobile430', 'tablet768', 'desktop1440', 'desktop1920'].includes(vp.name)) {
        await capture(page, `homepage-${vp.name}`);
      }
    });
  }
});

test.describe('auth canonical responsive', () => {
  for (const route of ['/login', '/register', '/forgot-password'] as const) {
    for (const vp of AUTH_VIEWPORTS) {
      test(`${route} @ ${vp.name}`, async ({ page }) => {
        await page.setViewportSize({ width: vp.width, height: vp.height });
        const response = await page.goto(route, { waitUntil: 'domcontentloaded' });
        expect(response?.status()).toBe(200);
        await expect(page.locator('.jp-auth-page, .jp-auth-card').first()).toBeVisible();
        await assertNoForbiddenMarkers(page);
        const overflow = await getOverflowMetrics(page);
        expect(overflow.hasOverflow).toBe(false);
        if (route === '/login') {
          await capture(page, `login-${vp.name}`);
        }
        if (route === '/register' && vp.name === 'mobile390') {
          await capture(page, 'register-mobile390');
        }
      });
    }
  }

  test('login then home keeps canonical architecture', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await loginWithJetpkOtp(page, 'customer@ota.demo', 'password');
    await page.goto('/', { waitUntil: 'domcontentloaded' });
    await assertNoForbiddenMarkers(page);
    await expect(page.locator('.hero')).toBeVisible();
    await expect(page.locator('.jp-auth-page')).toHaveCount(0);
  });
});

test.describe('non-home responsive', () => {
  for (const vp of AUTH_VIEWPORTS) {
    test(`support and lookup @ ${vp.name}`, async ({ page }) => {
      await page.setViewportSize({ width: vp.width, height: vp.height });
      for (const route of ['/support', '/lookup-booking'] as const) {
        const response = await page.goto(route, { waitUntil: 'domcontentloaded' });
        expect(response?.status()).toBe(200);
        await assertNoForbiddenMarkers(page);
        const overflow = await getOverflowMetrics(page);
        expect(overflow.hasOverflow).toBe(false);
      }
    });

    test(`flights results redirect stays JetPakistan @ ${vp.name}`, async ({ page }) => {
      await page.setViewportSize({ width: vp.width, height: vp.height });
      const response = await page.goto('/flights/results', { waitUntil: 'domcontentloaded' });
      expect(response?.status()).toBeLessThan(500);
      await assertNoForbiddenMarkers(page);
    });
  }
});

test.describe('no legacy mobile shell', () => {
  test('view-preference routes are gone', async ({ request }) => {
    for (const route of ['/mobile-view', '/desktop-view', '/view-preference/mobile', '/view-preference/desktop']) {
      const response = await request.get(route, { maxRedirects: 0 });
      expect([404, 405, 302, 301]).toContain(response.status());
    }
  });
});

test.describe('first-load layout stability', () => {
  for (const vp of [
    { name: 'mobile390', width: 390, height: 844 },
    { name: 'desktop1440', width: 1440, height: 900 },
  ] as const) {
    test(`cold load stability @ ${vp.name}`, async ({ browser }) => {
      const context = await browser.newContext({
        viewport: { width: vp.width, height: vp.height },
        storageState: undefined,
      });
      const page = await context.newPage();
      await context.route('**/*', (route) => route.continue());

      const response = await page.goto('/', { waitUntil: 'domcontentloaded' });
      expect(response?.status()).toBe(200);
      const initialHeroBox = await page.locator('.hero').boundingBox();
      await page.waitForLoadState('networkidle');
      const finalHeroBox = await page.locator('.hero').boundingBox();
      expect(initialHeroBox).not.toBeNull();
      expect(finalHeroBox).not.toBeNull();
      if (initialHeroBox && finalHeroBox) {
        expect(Math.abs(initialHeroBox.height - finalHeroBox.height)).toBeLessThan(80);
      }
      await assertNoForbiddenMarkers(page);
      await context.close();
    });
  }
});

test.describe('no floating toggle', () => {
  test('homepage has no mobile/desktop toggle controls', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('[data-testid="jp-desktop-mobile-app-toggle"]')).toHaveCount(0);
    await expect(page.locator('[data-testid="ota-desktop-mobile-toggle"]')).toHaveCount(0);
    await expect(page.getByRole('link', { name: /^Mobile App$/i })).toHaveCount(0);
    await expect(page.getByRole('link', { name: /^Desktop$/i })).toHaveCount(0);
  });
});

test.describe('no hero CTA', () => {
  for (const vp of [
    { name: 'mobile390', width: 390, height: 844 },
    { name: 'desktop1440', width: 1440, height: 900 },
  ] as const) {
    test(`hero has no legacy CTAs @ ${vp.name}`, async ({ page }) => {
      await page.setViewportSize({ width: vp.width, height: vp.height });
      await page.goto('/', { waitUntil: 'domcontentloaded' });
      await assertNoHeroCtas(page);
    });
  }
});

test.describe('no brand leakage', () => {
  const routes = ['/', '/login', '/support'] as const;
  for (const route of routes) {
    test(`no prohibited branding on ${route}`, async ({ page }) => {
      await page.goto(route, { waitUntil: 'domcontentloaded' });
      const html = await page.content();
      for (const brand of ['Parwaaz', 'haseeb-master', 'support@haseebasif.com', 'YoursDomain', 'YD Travel']) {
        expect(html).not.toContain(brand);
      }
    });
  }
});

test.describe('CMS Admin/public parity', () => {
  test.use({ storageState: 'storage/test-results/admin-page-settings-fresh-auth.json' });

  test('admin page settings panels and no hero CTA fields @ 1440x900', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/admin/page-settings/home', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('[data-jp-page-editor]')).toBeVisible({ timeout: 30_000 });
    await expect(page.locator('#hero-cta1-text')).toHaveCount(0);
    await expect(page.locator('#hero-cta2-url')).toHaveCount(0);
    for (const section of ['routes', 'destinations', 'featured-deals', 'group-cards', 'support-cta', 'trust']) {
      await expect(page.locator(`[data-jp-section-nav] [data-jp-section="${section}"]`)).toBeVisible();
    }
    await expect(page.locator('[data-jp-section-nav] [data-jp-section="groups"]')).toHaveCount(0);
    await capture(page, 'admin-page-settings-1440x900');
  });

  test('admin page settings responsive @ 768x1024', async ({ page }) => {
    await page.setViewportSize({ width: 768, height: 1024 });
    await page.goto('/admin/page-settings/home', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('[data-jp-page-editor]')).toBeVisible({ timeout: 30_000 });
    const overflow = await getOverflowMetrics(page);
    expect(overflow.hasOverflow).toBe(false);
    await capture(page, 'admin-page-settings-768x1024');
  });
});
