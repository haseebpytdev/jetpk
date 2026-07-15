import type { Page } from '@playwright/test';
import { ensureAccountDropdownOpen } from './interactions';
import type { AuditFailure, AuditRole } from './types';
import type { InteractionContext } from './interactions';

function permissionFailure(
  ctx: InteractionContext,
  selector: string,
  issue: string,
): AuditFailure {
  return {
    id: `${ctx.role}:${ctx.pageKey}:permission:${ctx.browser}:${ctx.viewport}`,
    category: 'navigation',
    severity: 'High',
    role: ctx.role,
    pageKey: ctx.pageKey,
    pagePath: ctx.pagePath,
    browser: ctx.browser,
    viewport: ctx.viewport,
    selector,
    issue,
    suggestedFix: 'Align Blade visibility with AgentPermission middleware; staff must not see balance or agency edit without explicit grants.',
  };
}

async function openAccountDropdownForChecks(page: Page, skipOpen = false): Promise<boolean> {
  const viewport = page.viewportSize();
  const variant = viewport && viewport.width < 992 ? 'mobile' : 'desktop';

  const alreadyOpen = await page.evaluate((v) => {
    const menu = document.querySelector(`[data-testid="account-dropdown-${v}"]`);
    const trigger = menu?.querySelector('[data-account-trigger]') as HTMLElement | null;
    const dropdown = menu?.querySelector('[data-account-dropdown]') as HTMLElement | null;
    return Boolean(
      dropdown &&
        !dropdown.hidden &&
        trigger?.getAttribute('aria-expanded') === 'true' &&
        dropdown.getBoundingClientRect().width > 0,
    );
  }, variant);

  if (alreadyOpen) {
    return true;
  }

  if (skipOpen) {
    return false;
  }

  return ensureAccountDropdownOpen(page, { allowProgrammaticFallback: true });
}

export async function runAgentPermissionUiChecks(
  page: Page,
  ctx: InteractionContext,
  options: { skipOpen?: boolean } = {},
): Promise<AuditFailure[]> {
  if (ctx.pageKey !== 'dashboard' && ctx.pageKey !== 'agency' && ctx.pageKey !== 'agency-edit') {
    return [];
  }

  const viewport = page.viewportSize();
  const variant = viewport && viewport.width < 992 ? 'mobile' : 'desktop';

  const dropdownChecks = ctx.pageKey === 'dashboard';

  if (ctx.pageKey === 'dashboard' || ctx.pageKey === 'agency') {
    const dropdownOpen = await openAccountDropdownForChecks(page, options.skipOpen);
    if (dropdownChecks && !dropdownOpen) {
      return [
        permissionFailure(
          ctx,
          '[data-account-dropdown]',
          'Account dropdown not open for permission inspection',
        ),
      ];
    }
  }

  const failures: AuditFailure[] = [];
  const state = await page.evaluate((menuVariant) => {
    const menu = document.querySelector(`[data-testid="account-dropdown-${menuVariant}"]`);
    const visibleInMenu = (sel: string) => {
      const el = menu?.querySelector(sel) ?? document.querySelector(sel);
      if (!el) return false;
      const html = el as HTMLElement;
      const rect = html.getBoundingClientRect();
      const style = window.getComputedStyle(html);
      return (
        rect.width > 0 &&
        rect.height > 0 &&
        style.display !== 'none' &&
        style.visibility !== 'hidden' &&
        parseFloat(style.opacity || '1') > 0.05
      );
    };

    return {
      balance: visibleInMenu('[data-testid="account-dropdown-balance"]'),
      agencySettings: visibleInMenu('[data-testid="account-dropdown-link-agency-settings"]'),
      agencyEditLink: visibleInMenu('[data-testid="agent-agency-edit-link"]'),
      headerBrand: (document.querySelector('[data-testid="header-brand-name"]')?.textContent || '').trim(),
    };
  }, variant);

  if (ctx.role === 'agent_staff_restricted' && dropdownChecks) {
    if (state.balance) {
      failures.push(
        permissionFailure(ctx, '[data-testid="account-dropdown-balance"]', 'Balance box visible for restricted staff (expected hidden)'),
      );
    }
    if (state.agencySettings) {
      failures.push(
        permissionFailure(
          ctx,
          '[data-testid="account-dropdown-link-agency-settings"]',
          'Agency Settings link visible for restricted staff (expected hidden)',
        ),
      );
    }
    if (!state.headerBrand.includes('JetPakistan')) {
      failures.push(
        permissionFailure(ctx, '[data-testid="header-brand-name"]', `Header brand expected JetPakistan, got "${state.headerBrand}"`),
      );
    }
  }

  if (ctx.role === 'agent_staff_full') {
    if (dropdownChecks && !state.balance) {
      failures.push(
        permissionFailure(ctx, '[data-testid="account-dropdown-balance"]', 'Balance box not visible for broad staff (expected visible)'),
      );
    }
    if (dropdownChecks && !state.agencySettings) {
      failures.push(
        permissionFailure(
          ctx,
          '[data-testid="account-dropdown-link-agency-settings"]',
          'Agency Settings link not visible for broad staff (expected view-only link)',
        ),
      );
    }
    if (ctx.pageKey === 'agency' && state.agencyEditLink) {
      failures.push(
        permissionFailure(ctx, '[data-testid="agent-agency-edit-link"]', 'Agency edit link visible for staff (expected hidden)'),
      );
    }
    if (!state.headerBrand.includes('JetPakistan')) {
      failures.push(
        permissionFailure(ctx, '[data-testid="header-brand-name"]', `Header brand expected JetPakistan, got "${state.headerBrand}"`),
      );
    }
  }

  if (ctx.role === 'agent' && dropdownChecks) {
    if (!state.balance) {
      failures.push(
        permissionFailure(ctx, '[data-testid="account-dropdown-balance"]', 'Balance box not visible for agent admin (expected visible)'),
      );
    }
    if (!state.agencySettings) {
      failures.push(
        permissionFailure(
          ctx,
          '[data-testid="account-dropdown-link-agency-settings"]',
          'Agency Settings link not visible for agent admin (expected visible)',
        ),
      );
    }
  }

  return failures;
}

export type { AuditRole };
