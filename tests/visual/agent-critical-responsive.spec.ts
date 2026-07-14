import { test, expect } from '@playwright/test';
import { readAuthStatus, storageStateForRole } from './helpers/auth';
import { runAgentPermissionUiChecks } from './helpers/agent-permission-checks';
import { testAccountDropdown } from './helpers/interactions';
import {
  assertClickableActionsHorizontallyVisible,
} from './helpers/critical-checks';
import { assertNoHorizontalOverflow, assertNoMajorOverlap, getOverflowMetrics } from './helpers/layout-checks';
import type { AuditRole, FailureCategory } from './helpers/types';
import { AGENT_CRITICAL_VIEWPORTS } from './helpers/critical-viewports';

const NAV_TIMEOUT = 20_000;

type CriticalPage = { key: string; path: string; permissionOnDashboard?: boolean };

const AGENT_ADMIN_PAGES: CriticalPage[] = [
  { key: 'dashboard', path: '/agent', permissionOnDashboard: true },
  { key: 'bookings', path: '/agent/bookings' },
  { key: 'bookings-create', path: '/agent/bookings/create' },
  { key: 'wallet', path: '/agent/wallet' },
  { key: 'ledger', path: '/agent/ledger' },
  { key: 'deposits', path: '/agent/deposits' },
  { key: 'deposits-create', path: '/agent/deposits/create' },
  { key: 'agency', path: '/agent/agency' },
  { key: 'agency-edit', path: '/agent/agency/edit' },
  { key: 'staff', path: '/agent/staff' },
  { key: 'support-tickets', path: '/agent/support/tickets' },
  { key: 'travelers', path: '/agent/travelers' },
  { key: 'profile', path: '/profile' },
];

const AGENT_STAFF_RESTRICTED_PAGES: CriticalPage[] = [
  { key: 'dashboard', path: '/agent', permissionOnDashboard: true },
  { key: 'profile', path: '/profile' },
];

const AGENT_STAFF_FULL_PAGES: CriticalPage[] = [
  { key: 'dashboard', path: '/agent', permissionOnDashboard: true },
  { key: 'deposits', path: '/agent/deposits' },
  { key: 'travelers', path: '/agent/travelers' },
  { key: 'ledger', path: '/agent/ledger' },
  { key: 'agency', path: '/agent/agency' },
  { key: 'profile', path: '/profile' },
];

const ROLE_PAGES: Record<string, CriticalPage[]> = {
  agent: AGENT_ADMIN_PAGES,
  agent_staff_restricted: AGENT_STAFF_RESTRICTED_PAGES,
  agent_staff_full: AGENT_STAFF_FULL_PAGES,
};

function roleReady(role: AuditRole): boolean {
  if (role === 'guest') return true;
  const status = readAuthStatus().find((a) => a.role === role);
  return status?.success === true && Boolean(storageStateForRole(role));
}

function severityRank(severity: string): number {
  return { Critical: 0, High: 1, Medium: 2, Low: 3 }[severity] ?? 9;
}

for (const [role, pages] of Object.entries(ROLE_PAGES)) {
  const auditRole = role as AuditRole;

  test.describe(`${role} critical responsive`, () => {
    test.skip(!roleReady(auditRole), `Auth storage missing for ${role}`);

    test.use({
      storageState: storageStateForRole(auditRole) ?? undefined,
    });

    for (const pageDef of pages) {
      for (const vp of AGENT_CRITICAL_VIEWPORTS) {
        test(`${pageDef.key} @ ${vp.name}`, async ({ page }, testInfo) => {
          await page.setViewportSize({ width: vp.width, height: vp.height });
          const response = await page.goto(pageDef.path, {
            waitUntil: 'domcontentloaded',
            timeout: NAV_TIMEOUT,
          });
          const status = response?.status() ?? 0;
          expect(status).toBeGreaterThan(0);
          expect(status).toBeLessThan(500);

          await page.evaluate(() => window.scrollTo(0, 0));

          const ctx = {
            role: auditRole,
            pageKey: pageDef.key,
            pagePath: pageDef.path,
            browser: testInfo.project.name,
            viewport: vp.name as import('./helpers/types').ViewportName,
          };

          const overflow = await getOverflowMetrics(page);
          expect(overflow.hasOverflow).toBe(false);

          const failures = [
            ...(await assertNoHorizontalOverflow(page, ctx)),
            ...(await assertNoMajorOverlap(page, ctx)),
            ...(await assertClickableActionsHorizontallyVisible(page, ctx)),
          ];

          if (pageDef.permissionOnDashboard) {
            const dropdown = await testAccountDropdown(page, ctx, '', { closeAfter: false });
            failures.push(...dropdown.failures);
            failures.push(
              ...(await runAgentPermissionUiChecks(page, ctx, {
                skipOpen: dropdown.success && !dropdown.skipped,
              })),
            );
            await page.keyboard.press('Escape').catch(() => undefined);
          }

          const highPlus = failures.filter((f) => severityRank(f.severity) <= 1);
          if (highPlus.length > 0) {
            const grouped = highPlus.reduce(
              (acc, f) => {
                acc[f.category] = (acc[f.category] ?? 0) + 1;
                return acc;
              },
              {} as Record<FailureCategory, number>,
            );
            expect(highPlus, JSON.stringify(grouped, null, 2)).toHaveLength(0);
          }
        });
      }
    }
  });
}
