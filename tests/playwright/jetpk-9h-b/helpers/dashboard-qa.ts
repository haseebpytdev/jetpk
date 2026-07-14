import type { Page, TestInfo } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

const auditDir = path.join('storage', 'app', 'audits', 'jetpk-9h-b');
const forbiddenBrands = ['Parwaaz', 'YD Travel', 'haseeb-master', 'haseebasif.com'];

export type PageSpec = {
  path: string;
  heading?: RegExp | string;
  shell?: string;
  expectStatus?: number | number[];
};

export function collectPageSignals(page: Page) {
  const consoleErrors: string[] = [];
  const networkFailures: { url: string; status: number }[] = [];

  page.on('console', (msg) => {
    if (msg.type() === 'error') {
      const text = msg.text();
      if (text.includes('favicon')) {
        return;
      }
      if (text.includes('404') && text.includes('Failed to load resource')) {
        return;
      }
      if (
        text.includes('net::ERR_CONNECTION') ||
        text.includes('net::ERR_NETWORK_CHANGED') ||
        text.includes('Failed to load resource: net::')
      ) {
        return;
      }
      consoleErrors.push(text);
    }
  });

  page.on('response', (response) => {
    const url = response.url();
    try {
      const pageOrigin = new URL(page.url()).origin;
      if (!url.startsWith(pageOrigin)) {
        return;
      }
    } catch {
      return;
    }
    const status = response.status();
    if (status >= 500 && !url.includes('favicon')) {
      networkFailures.push({ url, status });
    }
  });

  return { consoleErrors, networkFailures };
}

export async function assertDashboardPage(page: Page, spec: PageSpec, testInfo: TestInfo): Promise<void> {
  const { consoleErrors, networkFailures } = collectPageSignals(page);
  const response = await page.goto(spec.path, { waitUntil: 'domcontentloaded', timeout: 60_000 });
  const status = response?.status() ?? 0;
  const allowed = Array.isArray(spec.expectStatus) ? spec.expectStatus : [spec.expectStatus ?? 200];

  if (!allowed.includes(status)) {
    throw new Error(`Unexpected HTTP ${status} for ${spec.path}`);
  }

  await page.locator('body').waitFor({ state: 'visible' });

  await page
    .waitForFunction(
      () => {
        const images = Array.from(document.images).filter((img) => img.src && !img.src.toLowerCase().includes('.svg'));
        return images.every((img) => img.complete);
      },
      undefined,
      { timeout: 15_000 },
    )
    .catch(() => {});

  if (spec.shell === 'auto') {
    await Promise.race([
      page.locator('#jp-dash-sidebar').first().waitFor({ state: 'visible', timeout: 15_000 }),
      page.locator('.jp-portal__top').first().waitFor({ state: 'visible', timeout: 15_000 }),
    ]);
  } else if (spec.shell) {
    await page.locator(spec.shell).first().waitFor({ state: 'visible', timeout: 15_000 });
  }

  if (spec.heading) {
    const heading = typeof spec.heading === 'string' ? new RegExp(spec.heading, 'i') : spec.heading;
    await page.getByRole('heading', { name: heading }).first().waitFor({ state: 'visible', timeout: 15_000 }).catch(() => {
      // Fallback: any h1 visible
      return page.locator('h1').first().waitFor({ state: 'visible', timeout: 5_000 });
    });
  }

  const bodyText = await page.locator('body').innerText();
  for (const leak of forbiddenBrands) {
    if (bodyText.includes(leak)) {
      throw new Error(`Forbidden branding leak "${leak}" on ${spec.path}`);
    }
  }

  const overflow = await page.evaluate(() => {
    const doc = document.documentElement;
    const body = document.body;
    return doc.scrollWidth > doc.clientWidth + 2 || body.scrollWidth > body.clientWidth + 2;
  });
  if (overflow) {
    throw new Error(`Horizontal overflow on ${spec.path}`);
  }

  const brokenImages = await page.evaluate(() =>
    Array.from(document.images)
      .filter((img) => {
        if (!img.src) {
          return false;
        }
        if (img.src.toLowerCase().includes('.svg')) {
          return false;
        }
        return img.naturalWidth === 0 && img.naturalHeight === 0;
      })
      .map((img) => img.src),
  );
  if (brokenImages.length > 0) {
    throw new Error(`Broken images on ${spec.path}: ${brokenImages.join(', ')}`);
  }

  const shotDir = path.join('tests', 'playwright', 'artifacts', 'jetpk-9h-b', 'screenshots', testInfo.project.name);
  fs.mkdirSync(shotDir, { recursive: true });
  const safeName = spec.path.replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '') || 'root';
  await page.screenshot({ path: path.join(shotDir, `${safeName}.png`), fullPage: true });

  const report = {
    path: spec.path,
    project: testInfo.project.name,
    status,
    consoleErrors,
    networkFailures,
    brokenImages,
    horizontalOverflow: overflow,
  };

  fs.mkdirSync(auditDir, { recursive: true });
  fs.appendFileSync(path.join(auditDir, 'page-results.jsonl'), `${JSON.stringify(report)}\n`);

  if (consoleErrors.length > 0) {
    throw new Error(`Console errors on ${spec.path}: ${consoleErrors.join(' | ')}`);
  }
  if (networkFailures.some((f) => f.status >= 500)) {
    throw new Error(`5xx network failures on ${spec.path}`);
  }
}

export const adminPages: PageSpec[] = [
  { path: '/admin', shell: '#jp-dash-sidebar' },
  { path: '/admin/bookings', shell: '#jp-dash-sidebar' },
  { path: '/admin/customers', shell: '#jp-dash-sidebar' },
  { path: '/admin/users', shell: '#jp-dash-sidebar' },
  { path: '/admin/agents', shell: '#jp-dash-sidebar' },
  { path: '/admin/api-settings', shell: '#jp-dash-sidebar' },
  { path: '/admin/api-settings/create?provider=sabre', shell: '#jp-dash-sidebar' },
  { path: '/admin/api-settings/create?provider=pia_ndc', shell: '#jp-dash-sidebar' },
  { path: '/admin/api-settings/create?provider=airblue', shell: '#jp-dash-sidebar' },
  { path: '/admin/group-ticketing', shell: '#jp-dash-sidebar' },
  { path: '/admin/reports', shell: '#jp-dash-sidebar' },
  { path: '/admin/accounting/ledger', shell: '#jp-dash-sidebar' },
  { path: '/admin/ledger', shell: '#jp-dash-sidebar' },
  { path: '/admin/markups', shell: '#jp-dash-sidebar' },
  { path: '/admin/support/tickets', shell: '#jp-dash-sidebar' },
  { path: '/admin/settings/communications', shell: '#jp-dash-sidebar' },
  { path: '/admin/reports/supplier-diagnostics', shell: '#jp-dash-sidebar' },
  { path: '/admin/settings', shell: '#jp-dash-sidebar' },
  { path: '/admin/settings/branding', shell: '#jp-dash-sidebar' },
  { path: '/admin/settings/media', shell: '#jp-dash-sidebar' },
  { path: '/admin/page-settings', shell: '#jp-dash-sidebar' },
  { path: '/admin/page-settings/home', shell: '#jp-dash-sidebar' },
  { path: '/profile', shell: 'auto' },
];

export const staffPages: PageSpec[] = [
  { path: '/staff', shell: '#jp-dash-sidebar' },
  { path: '/staff/bookings', shell: '#jp-dash-sidebar' },
  { path: '/profile', shell: 'auto' },
];

export const agentPages: PageSpec[] = [
  { path: '/agent', shell: '.jp-portal__top' },
  { path: '/agent/bookings', shell: '.jp-portal__top' },
  { path: '/agent/agency', shell: '.jp-portal__top' },
  { path: '/profile', shell: 'auto' },
];

export const customerPages: PageSpec[] = [
  { path: '/customer', shell: '.jp-portal__top' },
  { path: '/customer/bookings', shell: '.jp-portal__top' },
  { path: '/customer/support', shell: '.jp-portal__top' },
  { path: '/profile', shell: 'auto' },
];
