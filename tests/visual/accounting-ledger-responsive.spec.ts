import { test, expect } from '@playwright/test';
import { readAuthStatus, storageStateForRole } from './helpers/auth';
import { assertNoHorizontalOverflow, getOverflowMetrics } from './helpers/layout-checks';
import type { AuditRole } from './helpers/types';

const VIEWPORTS = [
  { name: 'mobile390', width: 390, height: 844 },
  { name: 'tablet768', width: 768, height: 1024 },
  { name: 'desktop1366', width: 1366, height: 768 },
] as const;

const PAGES: { role: AuditRole; key: string; path: string }[] = [
  { role: 'admin', key: 'admin-accounting-ledger', path: '/admin/accounting/ledger' },
  { role: 'admin', key: 'admin-accounting-reconciliation', path: '/admin/accounting/reconciliation' },
  { role: 'agent', key: 'agent-accounting-ledger', path: '/agent/accounting/ledger' },
];

function roleReady(role: AuditRole): boolean {
  const status = readAuthStatus().find((a) => a.role === role);
  return status?.success === true && Boolean(storageStateForRole(role));
}

for (const pageDef of PAGES) {
  test.describe(`${pageDef.key} responsive`, () => {
    test.skip(!roleReady(pageDef.role), `Auth storage missing for ${pageDef.role}`);

    test.use({
      storageState: storageStateForRole(pageDef.role) ?? undefined,
    });

    for (const vp of VIEWPORTS) {
      test(`${pageDef.key} @ ${vp.name}`, async ({ page }) => {
        await page.setViewportSize({ width: vp.width, height: vp.height });
        const response = await page.goto(pageDef.path, {
          waitUntil: 'domcontentloaded',
          timeout: 25_000,
        });
        const status = response?.status() ?? 0;
        expect(status).toBeGreaterThan(0);
        expect(status).toBeLessThan(500);

        await page.evaluate(() => window.scrollTo(0, 0));
        const overflow = await getOverflowMetrics(page);
        expect(overflow.hasOverflow).toBe(false);

        const failures = await assertNoHorizontalOverflow(page, {
          role: pageDef.role,
          pageKey: pageDef.key,
          pagePath: pageDef.path,
          browser: 'chromium',
          viewport: vp.name,
        });
        expect(failures).toHaveLength(0);

        const body = await page.locator('body').innerText();
        expect(body.length).toBeGreaterThan(20);
      });
    }
  });
}
