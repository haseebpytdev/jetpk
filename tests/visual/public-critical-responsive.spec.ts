import { test, expect } from '@playwright/test';
import { PUBLIC_CRITICAL_VIEWPORTS } from './helpers/critical-viewports';
import { gotoPublicPage } from './helpers/public-fast-navigation';
import {
  filterPublicCriticalFailures,
  runPublicCriticalChecks,
  type PublicCriticalContext,
} from './helpers/public-critical-checks';
import { PUBLIC_PAGES } from './route-manifest';

const PUBLIC_CRITICAL_KEYS = new Set([
  'home',
  'flights-search',
  'flights-results',
  'login',
  'register',
  'support',
  'lookup-booking',
]);

const PUBLIC_CRITICAL_PAGES = PUBLIC_PAGES.filter((p) => PUBLIC_CRITICAL_KEYS.has(p.key));

test.describe('guest public critical responsive', () => {
  for (const pageDef of PUBLIC_CRITICAL_PAGES) {
    for (const vp of PUBLIC_CRITICAL_VIEWPORTS) {
      test(`${pageDef.key} @ ${vp.name}`, async ({ page }, testInfo) => {
        await page.setViewportSize({ width: vp.width, height: vp.height });

        const response = await gotoPublicPage(page, pageDef.path, pageDef.key);
        const status = response?.status() ?? 0;
        expect(status, `HTTP status for ${pageDef.path}`).toBeGreaterThan(0);
        expect(status, `HTTP status for ${pageDef.path}`).toBeLessThan(500);

        const ctx: PublicCriticalContext = {
          role: 'guest',
          pageKey: pageDef.key,
          pagePath: pageDef.path,
          browser: testInfo.project.name,
          viewport: vp.name,
        };

        const failures = filterPublicCriticalFailures(await runPublicCriticalChecks(page, ctx));

        if (failures.length > 0) {
          const grouped = failures.reduce(
            (acc, f) => {
              acc[f.category] = (acc[f.category] ?? 0) + 1;
              return acc;
            },
            {} as Record<string, number>,
          );
          expect(failures, JSON.stringify(grouped, null, 2)).toHaveLength(0);
        }
      });
    }
  }
});
