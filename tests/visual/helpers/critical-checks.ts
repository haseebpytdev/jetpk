import type { Page } from '@playwright/test';
import type { AuditFailure, AuditRole, FailureCategory, Severity, ViewportName } from './types';
import { OVERFLOW_TOLERANCE_PX } from './layout-checks';

export type CriticalCheckContext = {
  role: AuditRole;
  pageKey: string;
  pagePath: string;
  browser: string;
  viewport: ViewportName;
};

function criticalFailure(
  category: FailureCategory,
  ctx: CriticalCheckContext,
  selector: string,
  issue: string,
  severity: Severity = 'High',
): AuditFailure {
  return {
    id: `${ctx.role}:${ctx.pageKey}:${category}:${ctx.browser}:${ctx.viewport}:${selector}`,
    category,
    severity,
    role: ctx.role,
    pageKey: ctx.pageKey,
    pagePath: ctx.pagePath,
    browser: ctx.browser,
    viewport: ctx.viewport,
    selector,
    issue,
    suggestedFix: 'Fix horizontal overflow: wrap action groups and keep tables inside .ota-account-table-wrap.',
  };
}

/** Flags actions clipped horizontally (not merely below the fold). */
export async function assertClickableActionsHorizontallyVisible(
  page: Page,
  ctx: CriticalCheckContext,
): Promise<AuditFailure[]> {
  const failures: AuditFailure[] = [];
  const clipped = await page.evaluate((tolerance) => {
    const vw = window.innerWidth;
    const bad: Array<{ selector: string; text: string; right: number; left: number }> = [];

    const selectors =
      '.ota-account-header-actions .ota-account-btn, .ota-account-toolbar .ota-account-btn, .ota-r-action-bar .ota-account-btn, .ota-account-table td .ota-account-btn, .ota-account-table td button';

    for (const el of Array.from(document.querySelectorAll(selectors))) {
      const html = el as HTMLElement;
      const text = (html.textContent || '').trim();
      if (!text || text.length > 60) continue;
      const style = window.getComputedStyle(html);
      if (style.display === 'none' || style.visibility === 'hidden' || parseFloat(style.opacity || '1') < 0.2) {
        continue;
      }
      const rect = html.getBoundingClientRect();
      if (rect.width <= 0 || rect.height <= 0) continue;

      let node: Element | null = html;
      let inTableScroll = false;
      while (node && node !== document.body) {
        const st = window.getComputedStyle(node);
        if (
          (st.overflowX === 'auto' || st.overflowX === 'scroll') &&
          (node.classList.contains('ota-account-table-wrap') ||
            node.classList.contains('table-responsive') ||
            node.classList.contains('ota-r-table-wrap'))
        ) {
          inTableScroll = true;
          break;
        }
        node = node.parentElement;
      }

      const horizOff = rect.right > vw + tolerance || rect.left < -tolerance;
      if (horizOff && !inTableScroll) {
        bad.push({
          selector: html.tagName.toLowerCase() + (html.className ? `.${String(html.className).split(/\s+/)[0]}` : ''),
          text: text.slice(0, 40),
          right: rect.right,
          left: rect.left,
        });
      }
      if (bad.length >= 6) break;
    }

    return bad;
  }, OVERFLOW_TOLERANCE_PX);

  for (const item of clipped) {
    failures.push(
      criticalFailure(
        'clickable_actions',
        ctx,
        item.selector,
        `Action "${item.text}" extends horizontally outside viewport (left=${Math.round(item.left)}, right=${Math.round(item.right)})`,
      ),
    );
  }

  return failures;
}

export async function openAccountDropdownForCritical(page: Page): Promise<void> {
  await page.evaluate(() => window.scrollTo(0, 0));
  const viewport = page.viewportSize();
  if (!viewport) return;

  const variant = viewport.width < 992 ? 'mobile' : 'desktop';
  const menu = page.locator(`[data-testid="account-dropdown-${variant}"]`).first();
  const trigger = menu.locator('[data-account-trigger]').first();
  if ((await trigger.count()) === 0) return;

  const expandedBefore = await trigger.getAttribute('aria-expanded');
  if (expandedBefore !== 'true') {
    await trigger.click({ timeout: 5000 }).catch(() => undefined);
  }
  const dropdown = menu.locator('[data-account-dropdown]').first();
  await dropdown.waitFor({ state: 'visible', timeout: 5000 }).catch(() => undefined);
}
