import type { Page } from '@playwright/test';
import type { AuditFailure, AuditRole, FailureCategory, OverflowElementInfo, Severity, ViewportName } from './types';

export const OVERFLOW_TOLERANCE_PX = 2;

/** Dashboard/admin table wrappers that intentionally scroll internally (see layouts/dashboard.blade.php). */
const SCROLLABLE_TABLE_WRAPPER_CLASSES = [
  'table-responsive',
  'ota-r-table-wrap',
  'ota-account-table-wrap',
  'responsive-table',
  'ota-agent-ledger-table-wrap',
  'bookings-table-wrap',
  'staff-table-wrap',
  'admin-table-scroll',
] as const;

export type LayoutCheckContext = {
  role: AuditRole;
  pageKey: string;
  pagePath: string;
  browser: string;
  viewport: ViewportName;
  screenshotDir?: string;
};

export type LayoutCheckOutcome = {
  passed: boolean;
  failures: AuditFailure[];
  warnings: Array<{ message: string }>;
  checksRun: number;
  checksPassed: number;
  checksFailed: number;
};

function failureId(category: FailureCategory, ctx: LayoutCheckContext, selector: string): string {
  return `${ctx.role}:${ctx.pageKey}:${ctx.browser}:${ctx.viewport}:${category}:${selector}`.replace(/\s+/g, '_');
}

function baseFailure(
  category: FailureCategory,
  ctx: LayoutCheckContext,
  selector: string,
  issue: string,
  severity: Severity,
  suggestedFix: string,
  details?: Record<string, unknown>,
): AuditFailure {
  return {
    id: failureId(category, ctx, selector),
    category,
    severity,
    role: ctx.role,
    pageKey: ctx.pageKey,
    pagePath: ctx.pagePath,
    browser: ctx.browser,
    viewport: ctx.viewport,
    selector,
    issue,
    suggestedFix,
    details,
  };
}

export async function getOverflowMetrics(page: Page): Promise<{
  docScrollWidth: number;
  bodyScrollWidth: number;
  innerWidth: number;
  hasOverflow: boolean;
}> {
  return page.evaluate((tolerance) => {
    const docScrollWidth = document.documentElement.scrollWidth;
    const bodyScrollWidth = document.body?.scrollWidth ?? docScrollWidth;
    const innerWidth = window.innerWidth;

    return {
      docScrollWidth,
      bodyScrollWidth,
      innerWidth,
      hasOverflow: Math.max(docScrollWidth, bodyScrollWidth) > innerWidth + tolerance,
    };
  }, OVERFLOW_TOLERANCE_PX);
}

export async function findOverflowingElements(page: Page): Promise<OverflowElementInfo[]> {
  return page.evaluate(
    ({ tolerance, wrapperClasses }) => {
    const vw = window.innerWidth;
    const results: OverflowElementInfo[] = [];
    const seen = new Set<Element>();

    const isInsideScrollableTableWrapper = (el: Element): boolean => {
      let node: Element | null = el;
      while (node && node !== document.body) {
        const style = window.getComputedStyle(node);
        const overflowX = style.overflowX;
        const isScrollableTableWrapper = wrapperClasses.some((cls) => node!.classList.contains(cls));
        if (
          (overflowX === 'auto' || overflowX === 'scroll') &&
          (isScrollableTableWrapper ||
            node.classList.contains('ota-account-subnav') ||
            node.classList.contains('ota-agent-nav') ||
            node.classList.contains('ota-account-subnav-wrap') ||
            node.classList.contains('ota-agent-subnav-wrap'))
        ) {
          return true;
        }
        node = node.parentElement;
      }

      return false;
    };

    const candidates = document.querySelectorAll('body *');
    for (const el of candidates) {
      if (seen.has(el)) continue;
      const html = el as HTMLElement;
      const style = window.getComputedStyle(html);
      if (style.display === 'none' || style.visibility === 'hidden' || style.opacity === '0') continue;
      const rect = html.getBoundingClientRect();
      if (rect.width <= 0 || rect.height <= 0) continue;
      if (isInsideScrollableTableWrapper(el)) continue;

      const overflowsRight = rect.right > vw + tolerance;
      const overflowsLeft = rect.left < -tolerance;
      if (!overflowsRight && !overflowsLeft) continue;

      seen.add(el);

      const selectorParts: string[] = [];
      if (html.id) selectorParts.push(`#${html.id}`);
      if (html.className && typeof html.className === 'string') {
        const cls = html.className.trim().split(/\s+/).slice(0, 3).join('.');
        if (cls) selectorParts.push(`${html.tagName.toLowerCase()}.${cls}`);
      }
      if (selectorParts.length === 0) selectorParts.push(html.tagName.toLowerCase());

      results.push({
        selector: selectorParts.join(' '),
        tag: html.tagName.toLowerCase(),
        className: typeof html.className === 'string' ? html.className : '',
        id: html.id || '',
        text: (html.textContent || '').trim().slice(0, 120),
        rect: {
          left: rect.left,
          top: rect.top,
          right: rect.right,
          bottom: rect.bottom,
          width: rect.width,
          height: rect.height,
        },
        styles: {
          width: style.width,
          minWidth: style.minWidth,
          maxWidth: style.maxWidth,
          position: style.position,
          overflow: style.overflow,
          overflowX: style.overflowX,
        },
      });

      if (results.length >= 12) break;
    }

    return results;
  },
    {
      tolerance: OVERFLOW_TOLERANCE_PX,
      wrapperClasses: [...SCROLLABLE_TABLE_WRAPPER_CLASSES],
    },
  );
}

export async function assertNoHorizontalOverflow(page: Page, ctx: LayoutCheckContext): Promise<AuditFailure[]> {
  const failures: AuditFailure[] = [];
  const metrics = await getOverflowMetrics(page);

  if (metrics.hasOverflow) {
    const offenders = await findOverflowingElements(page);
    const top = offenders[0];
    failures.push(
      baseFailure(
        'horizontal_overflow',
        ctx,
        top?.selector ?? 'document',
        `Horizontal overflow detected (scrollWidth ${Math.max(metrics.docScrollWidth, metrics.bodyScrollWidth)} > viewport ${metrics.innerWidth})`,
        'High',
        'Apply global overflow-x: clip on shell; wrap wide content in scroll containers (.ota-r-table-wrap, .table-responsive).',
        { metrics, offenders },
      ),
    );
  }

  return failures;
}

export async function assertMainLandmarksVisible(page: Page, ctx: LayoutCheckContext): Promise<AuditFailure[]> {
  const failures: AuditFailure[] = [];

  const landmarkState = await page.evaluate(() => {
    const isVisible = (el: Element | null) => {
      if (!el) return null;
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

    const header = document.querySelector('header, .ota-header, .navbar, [role="banner"]');
    const main = document.querySelector('main, [role="main"], .ota-main, .page-body, .page-wrapper');
    const footer = document.querySelector('footer, .ota-footer, [role="contentinfo"]');

    const blockingOverlay = Array.from(document.querySelectorAll('[data-mobile-nav-backdrop], .modal-backdrop, .overlay'))
      .find((el) => {
        const html = el as HTMLElement;
        const style = window.getComputedStyle(html);
        const rect = html.getBoundingClientRect();
        return (
          rect.width >= window.innerWidth * 0.9 &&
          rect.height >= window.innerHeight * 0.9 &&
          style.display !== 'none' &&
          parseFloat(style.opacity || '1') > 0.4 &&
          style.pointerEvents !== 'none'
        );
      });

    return {
      headerVisible: isVisible(header),
      mainVisible: isVisible(main),
      footerVisible: isVisible(footer),
      blockingOverlay: blockingOverlay
        ? (blockingOverlay as HTMLElement).className || blockingOverlay.tagName.toLowerCase()
        : null,
    };
  });

  if (landmarkState.mainVisible === false) {
    failures.push(
      baseFailure(
        'landmark',
        ctx,
        'main',
        'Main content landmark not visible',
        'Critical',
        'Ensure main content area is rendered and not hidden by layout/CSS on this viewport.',
        landmarkState,
      ),
    );
  }

  if (landmarkState.blockingOverlay) {
    failures.push(
      baseFailure(
        'landmark',
        ctx,
        landmarkState.blockingOverlay,
        'Full-screen overlay may be blocking page interaction',
        'Medium',
        'Close mobile nav/backdrop or dismiss modal overlay before assertions; check z-index/pointer-events.',
        landmarkState,
      ),
    );
  }

  return failures;
}

export async function assertNoMajorOverlap(page: Page, ctx: LayoutCheckContext): Promise<AuditFailure[]> {
  const failures: AuditFailure[] = [];

  const overlap = await page.evaluate(() => {
    const header = document.querySelector('header, .ota-header, .navbar-fixed-top, .navbar');
    const dropdown = document.querySelector('[data-account-dropdown]:not([hidden]), .dropdown-menu.show, .ota-account-dropdown:not([hidden])');
    if (!header || !dropdown) return null;

    const h = header.getBoundingClientRect();
    const d = (dropdown as HTMLElement).getBoundingClientRect();
    const style = window.getComputedStyle(dropdown as Element);
    if (style.display === 'none' || style.visibility === 'hidden') return null;

    const overlapY = Math.max(0, Math.min(h.bottom, d.bottom) - Math.max(h.top, d.top));
    const overlapX = Math.max(0, Math.min(h.right, d.right) - Math.max(h.left, d.left));
    if (overlapX > 20 && overlapY > 8) {
      return { headerBottom: h.bottom, dropdownTop: d.top, overlapY };
    }

    return null;
  });

  if (overlap) {
    failures.push(
      baseFailure(
        'header_footer_overlap',
        ctx,
        '[data-account-dropdown], .ota-account-dropdown',
        'Opened dropdown overlaps fixed header region',
        'Medium',
        'Raise dropdown z-index (≥2000) or adjust header stacking; verify account dropdown at 390px.',
        overlap,
      ),
    );
  }

  return failures;
}

export async function assertTablesSafe(page: Page, ctx: LayoutCheckContext): Promise<AuditFailure[]> {
  const failures: AuditFailure[] = [];

  const unsafeTables = await page.evaluate(
    ({ tolerance, wrapperClasses }) => {
    const vw = window.innerWidth;
    const unsafe: Array<{ selector: string; tableWidth: number; wrapped: boolean }> = [];

    for (const table of Array.from(document.querySelectorAll('table'))) {
      const rect = table.getBoundingClientRect();
      if (rect.width <= 0) continue;

      let wrapped = false;
      let node: Element | null = table.parentElement;
      while (node && node !== document.body) {
        const style = window.getComputedStyle(node);
        const hasScrollWrapperClass = wrapperClasses.some((cls) => node!.classList.contains(cls));
        if (style.overflowX === 'auto' || style.overflowX === 'scroll' || hasScrollWrapperClass) {
          wrapped = true;
          break;
        }
        node = node.parentElement;
      }

      if (rect.width > vw + tolerance && !wrapped) {
        const cls = (table.className || '').toString().trim().split(/\s+/).slice(0, 2).join('.');
        unsafe.push({
          selector: cls ? `table.${cls}` : 'table',
          tableWidth: rect.width,
          wrapped,
        });
      }
    }

    return unsafe.slice(0, 8);
  },
    {
      tolerance: OVERFLOW_TOLERANCE_PX,
      wrapperClasses: [...SCROLLABLE_TABLE_WRAPPER_CLASSES],
    },
  );

  for (const table of unsafeTables) {
    failures.push(
      baseFailure(
        'table_wrapper',
        ctx,
        table.selector,
        `Table width ${Math.round(table.tableWidth)}px exceeds viewport without scroll wrapper`,
        'High',
        'Wrap table in .ota-r-table-wrap / .ota-account-table-wrap / .table-responsive with overflow-x:auto.',
        table,
      ),
    );
  }

  return failures;
}

export async function assertFormsSafe(page: Page, ctx: LayoutCheckContext): Promise<AuditFailure[]> {
  const failures: AuditFailure[] = [];

  const unsafeControls = await page.evaluate((tolerance) => {
    const vw = window.innerWidth;
    const bad: Array<{ selector: string; right: number }> = [];

    for (const el of Array.from(document.querySelectorAll('input, select, textarea, button'))) {
      const html = el as HTMLElement;
      const style = window.getComputedStyle(html);
      if (style.display === 'none' || style.visibility === 'hidden') continue;
      const rect = html.getBoundingClientRect();
      if (rect.width <= 0) continue;
      if (rect.right > vw + tolerance) {
        const name = (html.getAttribute('name') || html.id || html.tagName).toLowerCase();
        bad.push({ selector: `${html.tagName.toLowerCase()}[name="${name}"]`, right: rect.right });
      }
      if (bad.length >= 8) break;
    }

    return bad;
  }, OVERFLOW_TOLERANCE_PX);

  for (const control of unsafeControls) {
    failures.push(
      baseFailure(
        'form_field',
        ctx,
        control.selector,
        `Form control extends beyond viewport (right=${Math.round(control.right)}px)`,
        'Medium',
        'Use max-width:100% on inputs; collapse multi-column form grids below 768px (.ota-r-form-grid).',
        control,
      ),
    );
  }

  return failures;
}

export async function assertCardsSafe(page: Page, ctx: LayoutCheckContext): Promise<AuditFailure[]> {
  const failures: AuditFailure[] = [];

  const unsafeCards = await page.evaluate((tolerance) => {
    const vw = window.innerWidth;
    const bad: Array<{ selector: string; width: number }> = [];

    for (const el of Array.from(
      document.querySelectorAll('.card, .ota-account-card, .ota-search-card, .ota-account-kpi, [class*="kpi"]'),
    )) {
      const html = el as HTMLElement;
      const rect = html.getBoundingClientRect();
      if (rect.width <= 0) continue;
      if (rect.right > vw + tolerance || rect.width > vw + tolerance) {
        const cls = (html.className || '').toString().trim().split(/\s+/).slice(0, 2).join('.');
        bad.push({ selector: cls ? `.${cls}` : html.tagName.toLowerCase(), width: rect.width });
      }
      if (bad.length >= 6) break;
    }

    return bad;
  }, OVERFLOW_TOLERANCE_PX);

  for (const card of unsafeCards) {
    failures.push(
      baseFailure(
        'cards',
        ctx,
        card.selector,
        `Card/panel width ${Math.round(card.width)}px overflows viewport`,
        'Medium',
        'Use width:100% + max-width on cards; verify grid columns collapse on mobile.',
        card,
      ),
    );
  }

  return failures;
}

export async function assertTextSafe(page: Page, ctx: LayoutCheckContext): Promise<AuditFailure[]> {
  const failures: AuditFailure[] = [];

  const longTextOffenders = await page.evaluate((tolerance) => {
    const vw = window.innerWidth;
    const bad: Array<{ selector: string; text: string; right: number }> = [];

    const candidates = document.querySelectorAll(
      'td, th, .ota-account-table td, .badge, .text-muted, [data-testid], a, p, span',
    );

    for (const el of Array.from(candidates)) {
      const html = el as HTMLElement;
      const text = (html.textContent || '').trim();
      if (text.length < 28) continue;
      const style = window.getComputedStyle(html);
      if (style.display === 'none' || style.visibility === 'hidden') continue;
      const rect = html.getBoundingClientRect();
      if (rect.width <= 0) continue;
      if (rect.right > vw + tolerance) {
        bad.push({
          selector: html.tagName.toLowerCase() + (html.className ? `.${String(html.className).split(/\s+/)[0]}` : ''),
          text: text.slice(0, 80),
          right: rect.right,
        });
      }
      if (bad.length >= 6) break;
    }

    return bad;
  }, OVERFLOW_TOLERANCE_PX);

  for (const item of longTextOffenders) {
    failures.push(
      baseFailure(
        'text_overflow',
        ctx,
        item.selector,
        `Long text may cause horizontal overflow: "${item.text}"`,
        'Low',
        'Apply .ota-r-text-safe (overflow-wrap:anywhere) or truncate with title attribute where appropriate.',
        item,
      ),
    );
  }

  return failures;
}

export async function assertClickableActionsVisible(page: Page, ctx: LayoutCheckContext): Promise<AuditFailure[]> {
  const failures: AuditFailure[] = [];

  await page.evaluate(() => window.scrollTo(0, 0));
  await page.waitForTimeout(80);

  type ActionProbe = {
    key: string;
    label: string;
    selector: string;
    rect: { top: number; bottom: number; left: number; right: number };
    viewport: { w: number; h: number };
    docHeight: number;
    inInitialViewport: boolean;
    horizontalClip: boolean;
    belowFold: boolean;
    reachableByScroll: boolean;
  };

  const probes = await page.evaluate((tolerance) => {
    const vw = window.innerWidth;
    const vh = window.innerHeight;
    const docH = document.documentElement.scrollHeight;
    const scrollY = window.scrollY;
    const selectors =
      'button, a.btn, .ota-account-btn, .ota-btn-primary, .ota-btn-wa, .ota-btn-primary-lg, [data-testid*="action"], .dropdown-toggle, .ota-r-action-bar a, .ota-r-action-bar button, .ota-booking-actions button, .ota-booking-actions a, .ota-customer-actions a, .ota-customer-actions button';

    const normalize = (text: string): string => text.trim().replace(/\s+/g, ' ').slice(0, 60);

    const isActionHidden = (el: Element): boolean => {
      let node: Element | null = el;
      while (node && node !== document.body) {
        if (node instanceof HTMLDetailsElement && !node.open) {
          return true;
        }
        if (node.hasAttribute('hidden') || node.getAttribute('aria-hidden') === 'true') {
          return true;
        }
        const style = window.getComputedStyle(node);
        if (style.display === 'none' || style.visibility === 'hidden') {
          return true;
        }
        node = node.parentElement;
      }

      return false;
    };

    const items: ActionProbe[] = [];

    for (const el of Array.from(document.querySelectorAll(selectors))) {
      const html = el as HTMLElement;
      if (isActionHidden(html)) continue;
      const label = normalize(html.textContent || '');
      if (!label || label.length > 60) continue;
      const style = window.getComputedStyle(html);
      if (style.display === 'none' || style.visibility === 'hidden' || parseFloat(style.opacity || '1') < 0.2) continue;
      const rect = html.getBoundingClientRect();
      if (rect.width <= 0 || rect.height <= 0) continue;

      const horizontalClip = rect.right > vw + tolerance || rect.left < -tolerance;
      const inInitialViewport =
        rect.top >= 0 &&
        rect.left >= 0 &&
        rect.bottom <= vh + tolerance &&
        rect.right <= vw + tolerance;
      const belowFold = rect.top >= vh;
      const absBottom = rect.bottom + scrollY;
      const reachableByScroll = absBottom <= docH + tolerance;

      items.push({
        key: label.toLowerCase(),
        label,
        selector:
          html.tagName.toLowerCase() +
          (html.className ? `.${String(html.className).split(/\s+/)[0]}` : ''),
        rect: {
          top: Math.round(rect.top),
          bottom: Math.round(rect.bottom),
          left: Math.round(rect.left),
          right: Math.round(rect.right),
        },
        viewport: { w: vw, h: vh },
        docHeight: docH,
        inInitialViewport,
        horizontalClip,
        belowFold,
        reachableByScroll,
      });
    }

    return items;
  }, OVERFLOW_TOLERANCE_PX);

  const grouped = new Map<string, ActionProbe[]>();
  for (const probe of probes) {
    const list = grouped.get(probe.key) ?? [];
    list.push(probe);
    grouped.set(probe.key, list);
  }

  for (const [key, group] of grouped) {
    if (group.some((p) => p.inInitialViewport && !p.horizontalClip)) {
      continue;
    }

    const primary = group.reduce((best, current) => {
      if (current.horizontalClip && !best.horizontalClip) return best;
      if (!current.horizontalClip && best.horizontalClip) return current;
      if (current.inInitialViewport && !best.inInitialViewport) return current;
      if (current.belowFold && !best.belowFold) return best;
      return current.rect.top < best.rect.top ? current : best;
    }, group[0]);

    if (primary.horizontalClip) {
      failures.push(
        baseFailure(
          'clickable_actions',
          ctx,
          primary.selector,
          `Important action "${primary.label}" is horizontally clipped (right=${primary.rect.right}, viewport=${primary.viewport.w})`,
          'High',
          'Wrap action rows (.ota-r-action-bar / .ota-customer-actions) and ensure buttons use max-width:100% on narrow screens.',
          { primary, groupSize: group.length, reason: 'horizontal_clip' },
        ),
      );
      continue;
    }

    if (primary.belowFold && primary.reachableByScroll) {
      await page.evaluate(() => window.scrollTo(0, 0));
      await page.waitForTimeout(80);

      const scrollTarget = await page.evaluate((labelKey) => {
        const normalize = (text: string): string => text.trim().replace(/\s+/g, ' ').slice(0, 60).toLowerCase();
        const isActionHidden = (el: Element): boolean => {
          let node: Element | null = el;
          while (node && node !== document.body) {
            if (node instanceof HTMLDetailsElement && !node.open) {
              return true;
            }
            if (node.hasAttribute('hidden') || node.getAttribute('aria-hidden') === 'true') {
              return true;
            }
            node = node.parentElement;
          }

          return false;
        };
        const selectors =
          'button, a.btn, .ota-account-btn, .ota-btn-primary, .ota-btn-wa, .ota-btn-primary-lg, [data-testid*="action"], .ota-booking-actions button, .ota-booking-actions a, .ota-customer-actions a, .ota-customer-actions button';

        for (const el of Array.from(document.querySelectorAll(selectors))) {
          const html = el as HTMLElement;
          if (isActionHidden(html)) continue;
          if (normalize(html.textContent || '') !== labelKey) continue;
          const style = window.getComputedStyle(html);
          if (style.display === 'none' || style.visibility === 'hidden') continue;
          html.scrollIntoView({ block: 'center', inline: 'nearest' });
          return true;
        }

        return false;
      }, key);

      if (!scrollTarget) {
        failures.push(
          baseFailure(
            'clickable_actions',
            ctx,
            primary.selector,
            `Important action "${primary.label}" could not be scrolled into view`,
            'High',
            'Ensure the action exists in the DOM and is not removed/hidden after render.',
            { primary, reason: 'scroll_target_missing' },
          ),
        );
        continue;
      }

      await page.waitForTimeout(120);

      const afterScroll = await page.evaluate(({ labelKey, tolerance }) => {
        const normalize = (text: string): string => text.trim().replace(/\s+/g, ' ').slice(0, 60).toLowerCase();
        const isActionHidden = (el: Element): boolean => {
          let node: Element | null = el;
          while (node && node !== document.body) {
            if (node instanceof HTMLDetailsElement && !node.open) {
              return true;
            }
            if (node.hasAttribute('hidden') || node.getAttribute('aria-hidden') === 'true') {
              return true;
            }
            node = node.parentElement;
          }

          return false;
        };
        const vw = window.innerWidth;
        const vh = window.innerHeight;
        const selectors =
          'button, a.btn, .ota-account-btn, .ota-btn-primary, .ota-btn-wa, .ota-btn-primary-lg, [data-testid*="action"], .ota-booking-actions button, .ota-booking-actions a, .ota-customer-actions a, .ota-customer-actions button';

        for (const el of Array.from(document.querySelectorAll(selectors))) {
          const html = el as HTMLElement;
          if (isActionHidden(html)) continue;
          if (normalize(html.textContent || '') !== labelKey) continue;
          const style = window.getComputedStyle(html);
          if (style.display === 'none' || style.visibility === 'hidden') continue;
          const rect = html.getBoundingClientRect();
          if (rect.width <= 0 || rect.height <= 0) continue;

          const horizontalClip = rect.right > vw + tolerance || rect.left < -tolerance;
          const inViewport =
            rect.top >= 0 &&
            rect.left >= 0 &&
            rect.bottom <= vh + tolerance &&
            rect.right <= vw + tolerance;

          let coveredBySticky = false;
          const cx = rect.left + rect.width / 2;
          const cy = rect.top + rect.height / 2;
          const topEl = document.elementFromPoint(cx, cy);
          if (topEl && topEl !== html && !html.contains(topEl)) {
            const topStyle = window.getComputedStyle(topEl as Element);
            if (
              (topStyle.position === 'fixed' || topStyle.position === 'sticky') &&
              parseFloat(topStyle.zIndex || '0') >= 100
            ) {
              coveredBySticky = true;
            }
          }

          return {
            inViewport,
            horizontalClip,
            coveredBySticky,
            rect: {
              top: Math.round(rect.top),
              bottom: Math.round(rect.bottom),
              left: Math.round(rect.left),
              right: Math.round(rect.right),
            },
            viewport: { w: vw, h: vh },
          };
        }

        return null;
      }, { labelKey: key, tolerance: OVERFLOW_TOLERANCE_PX });

      if (!afterScroll) {
        continue;
      }

      if (afterScroll.horizontalClip) {
        failures.push(
          baseFailure(
            'clickable_actions',
            ctx,
            primary.selector,
            `Important action "${primary.label}" remains horizontally clipped after scroll`,
            'High',
            'Fix action row/card overflow; keep buttons inside their container on this viewport.',
            { primary, afterScroll, reason: 'horizontal_clip_after_scroll' },
          ),
        );
        continue;
      }

      if (afterScroll.coveredBySticky) {
        failures.push(
          baseFailure(
            'clickable_actions',
            ctx,
            primary.selector,
            `Important action "${primary.label}" is covered by a sticky header/footer after scroll`,
            'High',
            'Add bottom padding for sticky action bars or reduce sticky overlap on booking/customer pages.',
            { primary, afterScroll, reason: 'sticky_cover_after_scroll' },
          ),
        );
        continue;
      }

      if (afterScroll.inViewport) {
        continue;
      }

      failures.push(
        baseFailure(
          'clickable_actions',
          ctx,
          primary.selector,
          `Important action "${primary.label}" is not visible after scroll (top=${afterScroll.rect.top}, viewport=${afterScroll.viewport.h})`,
          'High',
          'Verify the action is not clipped by overflow:hidden ancestors after scroll.',
          { primary, afterScroll, reason: 'not_visible_after_scroll' },
        ),
      );

      continue;
    }

    if (
      primary.rect.top < 0 ||
      primary.rect.bottom < 0 ||
      primary.rect.left > primary.viewport.w ||
      primary.rect.right < 0
    ) {
      failures.push(
        baseFailure(
          'clickable_actions',
          ctx,
          primary.selector,
          `Important action "${primary.label}" appears clipped or offscreen`,
          'High',
          'Ensure action bars wrap (.ota-r-action-bar) and primary buttons remain reachable on mobile.',
          { primary, groupSize: group.length, reason: 'offscreen' },
        ),
      );
    }
  }

  await page.evaluate(() => window.scrollTo(0, 0));

  return failures;
}

export async function runLayoutChecks(page: Page, ctx: LayoutCheckContext): Promise<LayoutCheckOutcome> {
  const checkFns = [
    assertNoHorizontalOverflow,
    assertMainLandmarksVisible,
    assertNoMajorOverlap,
    assertTablesSafe,
    assertFormsSafe,
    assertCardsSafe,
    assertTextSafe,
    assertClickableActionsVisible,
  ];

  const failures: AuditFailure[] = [];
  const warnings: Array<{ message: string }> = [];
  let checksPassed = 0;

  for (const fn of checkFns) {
    const result = await fn(page, ctx);
    if (result.length === 0) {
      checksPassed += 1;
    } else {
      failures.push(...result);
    }
  }

  return {
    passed: failures.length === 0,
    failures,
    warnings,
    checksRun: checkFns.length,
    checksPassed,
    checksFailed: checkFns.length - checksPassed,
  };
}
