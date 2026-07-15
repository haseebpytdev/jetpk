import fs from 'node:fs';
import { test } from '@playwright/test';
import { readAuthStatus, storageStateForRole } from './helpers/auth';
import { runLayoutChecks } from './helpers/layout-checks';
import { runInteractiveChecks } from './helpers/interactions';
import { appendPageResult } from './helpers/report';
import {
  captureFullPageScreenshot,
  ensureDirForFile,
  failureScreenshotPath,
  interactiveScreenshotDir,
  screenshotPath,
} from './helpers/screenshots';
import type { AuditPageDef, AuditRole, PageAuditResult } from './helpers/types';
import { ALL_VIEWPORTS, INTERACTIVE_VIEWPORTS, SCREENSHOT_VIEWPORTS } from './helpers/viewports';
import { prepareGuestBookingAuditSession } from './helpers/booking-audit-session';
import { activeRoleManifests, roleDirFor } from './route-manifest';

function isAuthReady(role: AuditRole): boolean {
  if (role === 'guest') return true;
  const status = readAuthStatus().find((a) => a.role === role);
  return status?.success === true && Boolean(storageStateForRole(role));
}

async function resolveDynamicBookingPath(
  page: import('@playwright/test').Page,
  listPath: string,
): Promise<string | null> {
  await page.goto(listPath, { waitUntil: 'domcontentloaded', timeout: 60_000 });
  const link = page
    .locator('table a[href*="/bookings/"], .ota-account-table a[href*="/bookings/"], a[href*="/bookings/"]')
    .first();
  if ((await link.count()) === 0) return null;
  return (await link.getAttribute('href')) ?? null;
}

async function auditPage(
  page: import('@playwright/test').Page,
  role: AuditRole,
  pageDef: AuditPageDef,
  browserName: string,
): Promise<void> {
  const roleDir = roleDirFor(role);
  let targetPath = pageDef.path;

  for (const viewportDef of ALL_VIEWPORTS) {
    await test.step(`${viewportDef.name}`, async () => {
      await page.setViewportSize({ width: viewportDef.width, height: viewportDef.height });

      if (pageDef.path.includes('{id}')) {
        const listPath = pageDef.path.split('/{id}')[0] || pageDef.path.replace('{id}', '');
        const resolved = await resolveDynamicBookingPath(page, listPath);
        if (!resolved) {
          appendPageResult({
            role,
            pageKey: pageDef.key,
            pagePath: pageDef.path,
            browser: browserName,
            viewport: viewportDef.name,
            status: 'skipped',
            checksRun: 0,
            checksPassed: 0,
            checksFailed: 0,
            failures: [],
            warnings: [],
            skippedReason: 'No booking rows in local database for detail page',
          });
          return;
        }
        targetPath = resolved;
      }

      const response = await page.goto(targetPath, { waitUntil: 'domcontentloaded', timeout: 60_000 });
      const status = response?.status() ?? 0;

      if (status >= 500 || status === 0) {
        appendPageResult({
          role,
          pageKey: pageDef.key,
          pagePath: targetPath,
          browser: browserName,
          viewport: viewportDef.name,
          status: 'failed',
          checksRun: 0,
          checksPassed: 0,
          checksFailed: 1,
          failures: [
            {
              id: `${role}:${pageDef.key}:http:${browserName}:${viewportDef.name}`,
              category: 'landmark',
              severity: 'Critical',
              role,
              pageKey: pageDef.key,
              pagePath: targetPath,
              browser: browserName,
              viewport: viewportDef.name,
              selector: 'HTTP response',
              issue: `Page returned HTTP ${status || 'error'} for ${targetPath}`,
              suggestedFix: 'Inspect Laravel logs for server error on this route; likely missing migration/view data for profile or role guard.',
            },
          ],
          warnings: [],
        });
        return;
      }

      await page.waitForTimeout(350);

      const ctx = {
        role,
        pageKey: pageDef.key,
        pagePath: targetPath,
        browser: browserName,
        viewport: viewportDef.name,
      };

      const layout = await runLayoutChecks(page, ctx);
      const failures = [...layout.failures];

      const shouldScreenshot =
        SCREENSHOT_VIEWPORTS.includes(viewportDef.name) || pageDef.highRisk || failures.length > 0;

      let shotPath: string | undefined;
      if (shouldScreenshot) {
        shotPath = await captureFullPageScreenshot(
          page,
          screenshotPath(roleDir, pageDef.key, browserName, viewportDef.name),
        );
      }

      if (failures.length > 0) {
        const failShot = failureScreenshotPath(roleDir, pageDef.key, browserName, viewportDef.name, 'layout');
        ensureDirForFile(failShot);
        await page.screenshot({ path: failShot, fullPage: true }).catch(() => undefined);
        for (const f of failures) {
          f.screenshotPath = f.screenshotPath ?? failShot;
        }
      }

      const interactiveScreenshots: string[] = [];
      if (pageDef.interactive && INTERACTIVE_VIEWPORTS.includes(viewportDef.name)) {
        const interactiveBase = interactiveScreenshotDir(roleDir, pageDef.key);
        const interactionResults = await runInteractiveChecks(page, ctx, interactiveBase, {
          includeAirport: pageDef.key === 'home' || pageDef.key === 'flights-search',
          includeCalendar: pageDef.key === 'home' || pageDef.key === 'flights-search',
        });

        for (const ir of interactionResults) {
          if (ir.screenshotPath) interactiveScreenshots.push(ir.screenshotPath);
          failures.push(...ir.failures);
        }
      }

      appendPageResult({
        role,
        pageKey: pageDef.key,
        pagePath: targetPath,
        browser: browserName,
        viewport: viewportDef.name,
        status: failures.length > 0 ? 'failed' : 'passed',
        checksRun: layout.checksRun,
        checksPassed: layout.checksPassed,
        checksFailed: layout.checksFailed,
        screenshotPath: shotPath,
        interactiveScreenshots,
        failures,
        warnings: layout.warnings.map((w) => ({
          role,
          pageKey: pageDef.key,
          browser: browserName,
          viewport: viewportDef.name,
          message: w.message,
        })),
      });
    });
  }
}

for (const manifest of activeRoleManifests()) {
  test.describe(`${manifest.label}`, () => {
    if (manifest.storageState && fs.existsSync(manifest.storageState) && isAuthReady(manifest.role)) {
      test.use({ storageState: manifest.storageState });
    }

    for (const pageDef of manifest.pages) {
      test(`${manifest.role}/${pageDef.key}`, async ({ page, browserName }) => {
        if (manifest.role !== 'guest' && !isAuthReady(manifest.role)) {
          test.skip(true, `Auth not ready for ${manifest.role}`);
        }

        if (
          pageDef.skipReason &&
          (pageDef.key === 'booking-review' || pageDef.key === 'booking-confirmation')
        ) {
          const sessionReady = await prepareGuestBookingAuditSession(page);
          if (!sessionReady) {
            test.skip(true, pageDef.skipReason);
          }
        }

        await auditPage(page, manifest.role, pageDef, browserName);
      });
    }
  });
}
