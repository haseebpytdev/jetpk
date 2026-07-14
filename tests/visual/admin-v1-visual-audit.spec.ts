import fs from 'node:fs';
import path from 'node:path';
import { test, expect } from '@playwright/test';
import { collectAdminPageMetrics, type AdminViewportName } from './helpers/admin-v1-visual-metrics';
import {
  writeAdminVisualAuditReport,
  type AdminVisualAuditPayload,
  type AdminVisualCapture,
} from './helpers/admin-v1-visual-report';
import { setupRoleAuth } from './helpers/auth';

const SCREENSHOT_ROOT = path.join(process.cwd(), 'docs/audits/admin-v1-visual/screenshots');

const VIEWPORTS: Array<{ name: AdminViewportName; width: number; height: number }> = [
  { name: 'desktop-1440', width: 1440, height: 900 },
  { name: 'laptop-1366', width: 1366, height: 768 },
  { name: 'tablet-1024', width: 1024, height: 768 },
  { name: 'mobile-390', width: 390, height: 844 },
];

type AdminAuditPage = {
  key: string;
  label: string;
  path: string;
  dynamic?: 'booking-detail';
};

const ADMIN_PAGES: AdminAuditPage[] = [
  { key: 'dashboard', label: 'Admin Dashboard', path: '/admin' },
  { key: 'bookings', label: 'Admin Bookings', path: '/admin/bookings' },
  { key: 'booking-show', label: 'Booking Detail', path: '/admin/bookings/{id}', dynamic: 'booking-detail' },
  { key: 'reports', label: 'Admin Reports', path: '/admin/reports' },
  { key: 'support-tickets', label: 'Support Tickets', path: '/admin/support/tickets' },
  { key: 'group-ticketing', label: 'Group Ticketing', path: '/admin/group-ticketing' },
  { key: 'users', label: 'Users Management', path: '/admin/users' },
  { key: 'api-settings', label: 'API / Supplier Settings', path: '/admin/api-settings' },
  { key: 'settings', label: 'Settings Hub', path: '/admin/settings' },
  { key: 'supplier-diagnostics', label: 'Supplier Diagnostics', path: '/admin/reports/supplier-diagnostics' },
];

function adminCredentials(): { email: string; password: string; source: string } {
  const email =
    process.env.PLAYWRIGHT_ADMIN_EMAIL ??
    process.env.OTA_AUDIT_ADMIN_EMAIL ??
    'admin@ota.demo';
  const password =
    process.env.PLAYWRIGHT_ADMIN_PASSWORD ??
    process.env.OTA_AUDIT_PASSWORD ??
    'password';

  const source = process.env.PLAYWRIGHT_ADMIN_EMAIL
    ? 'PLAYWRIGHT_ADMIN_*'
    : process.env.OTA_AUDIT_ADMIN_EMAIL
      ? 'OTA_AUDIT_*'
      : 'default demo fallback';

  return { email, password, source };
}

function screenshotFile(pageKey: string, viewport: AdminViewportName): string {
  return path.join(SCREENSHOT_ROOT, pageKey, `${viewport}.png`);
}

async function resolveBookingDetailPath(page: import('@playwright/test').Page): Promise<string | null> {
  await page.goto('/admin/bookings', { waitUntil: 'domcontentloaded', timeout: 60_000 });
  await page.waitForTimeout(500);

  const selectors = [
    'tr.ota-admin-click-row[data-href*="/admin/bookings/"]',
    'a[href*="/admin/bookings/"]:not([href*="/preview"])',
    '.booking-queue-card',
    'table a[href*="/bookings/"]',
  ];

  for (const selector of selectors) {
    const loc = page.locator(selector).first();
    if ((await loc.count()) === 0) continue;

    if (selector.includes('data-href')) {
      const href = await loc.getAttribute('data-href');
      if (href && !href.includes('/preview')) return href;
    }

    const href = await loc.getAttribute('href');
    if (href && href.includes('/bookings/') && !href.includes('/preview')) return href;
  }

  return null;
}

test.describe.configure({ mode: 'serial' });

test.describe('Admin v1 visual audit', () => {
  const captures: AdminVisualCapture[] = [];
  const skipped: AdminVisualAuditPayload['skipped'] = [];
  let authSource = 'unknown';
  let channelChecks: AdminVisualAuditPayload['channelChecks'] = {
    adminV1DashboardOk: false,
    adminV2PreviewSameAsV1: null,
    notes: [],
  };

  test.beforeAll(async ({ browser }) => {
    fs.mkdirSync(SCREENSHOT_ROOT, { recursive: true });

    const cred = adminCredentials();
    authSource = cred.source;

    const context = await browser.newContext();
    const page = await context.newPage();

    const authDir = path.join(process.cwd(), 'UI_test', '.auth');
    fs.mkdirSync(authDir, { recursive: true });
    const storagePath = path.join(authDir, 'admin-visual-audit.json');

    const result = await setupRoleAuth(page, {
      role: 'admin',
      email: cred.email,
      password: cred.password,
      storagePath,
    });

    if (!result.success) {
      await context.close();
      throw new Error(
        `Admin login failed (${authSource}). Set PLAYWRIGHT_ADMIN_EMAIL and PLAYWRIGHT_ADMIN_PASSWORD or ensure local demo admin exists.`,
      );
    }

    await context.storageState({ path: storagePath });
    await context.close();
  });

  test('capture admin pages across viewports', async ({ browser }) => {
    const cred = adminCredentials();
    const storagePath = path.join(process.cwd(), 'UI_test', '.auth', 'admin-visual-audit.json');

    const context = await browser.newContext({ storageState: storagePath });
    const page = await context.newPage();

    let bookingDetailPath: string | null = null;

    for (const pageDef of ADMIN_PAGES) {
      let targetPath = pageDef.path;

      if (pageDef.dynamic === 'booking-detail') {
        if (!bookingDetailPath) {
          bookingDetailPath = await resolveBookingDetailPath(page);
        }
        if (!bookingDetailPath) {
          skipped.push({
            pageKey: pageDef.key,
            path: pageDef.path,
            reason: 'No local booking rows found for detail capture',
          });
          continue;
        }
        targetPath = bookingDetailPath;
      }

      for (const vp of VIEWPORTS) {
        await page.setViewportSize({ width: vp.width, height: vp.height });
        const response = await page.goto(targetPath, { waitUntil: 'domcontentloaded', timeout: 60_000 });
        await page.waitForTimeout(400);

        const status = response?.status() ?? 0;
        const filePath = screenshotFile(pageDef.key, vp.name);
        fs.mkdirSync(path.dirname(filePath), { recursive: true });

        if (status >= 400 || status === 0) {
          skipped.push({
            pageKey: `${pageDef.key}:${vp.name}`,
            path: targetPath,
            reason: `HTTP ${status || 'error'}`,
          });
          continue;
        }

        await page.screenshot({ path: filePath, fullPage: true });
        const metrics = await collectAdminPageMetrics(page);

        captures.push({
          pageKey: pageDef.key,
          label: pageDef.label,
          path: targetPath,
          viewport: vp.name,
          screenshotPath: filePath,
          httpStatus: status,
          metrics,
        });
      }
    }

    // Channel safety: compare /admin vs /admin?ui=v2 at desktop
    await page.setViewportSize({ width: 1440, height: 900 });
    const v1 = await page.goto('/admin', { waitUntil: 'domcontentloaded', timeout: 60_000 });
    channelChecks.adminV1DashboardOk = (v1?.status() ?? 0) < 400;
    const v1Title = await page.locator('h1.page-title, .page-title').first().textContent();

    const v2 = await page.goto('/admin?ui=v2', { waitUntil: 'domcontentloaded', timeout: 60_000 });
    const v2Status = v2?.status() ?? 0;
    const v2Title = await page.locator('h1.page-title, .page-title').first().textContent();
    channelChecks.adminV2PreviewSameAsV1 =
      v2Status < 400 && (v1Title?.trim() ?? '') === (v2Title?.trim() ?? '');
    channelChecks.notes.push(
      v2Status < 400
        ? 'admin v2 preview query loads; no v2 overlay files — layout matches v1 (expected).'
        : 'admin v2 preview query did not load cleanly — review HTTP status.',
    );

    const v2Shot = screenshotFile('dashboard-ui-v2-check', 'desktop-1440');
    await page.screenshot({ path: v2Shot, fullPage: true });
    captures.push({
      pageKey: 'dashboard-ui-v2-check',
      label: 'Dashboard ui=v2 channel check',
      path: '/admin?ui=v2',
      viewport: 'desktop-1440',
      screenshotPath: v2Shot,
      httpStatus: v2Status,
      metrics: await collectAdminPageMetrics(page),
    });

    await context.close();
    expect(captures.length).toBeGreaterThan(0);
  });

  test.afterAll(async () => {
    const baseUrl =
      process.env.LOCAL_OTA_URL ?? process.env.PLAYWRIGHT_BASE_URL ?? process.env.APP_URL ?? 'http://127.0.0.1:8000';

    const payload: AdminVisualAuditPayload = {
      generatedAt: new Date().toISOString(),
      baseUrl,
      playwrightAvailable: true,
      authMethod: authSource,
      pagesRequested: ADMIN_PAGES.map((p) => p.path),
      captures,
      skipped,
      channelChecks,
    };

    const { mdPath } = writeAdminVisualAuditReport(payload);
    // eslint-disable-next-line no-console
    console.log(`Admin visual audit report: ${mdPath}`);
  });
});
