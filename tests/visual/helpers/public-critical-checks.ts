import type { Page } from '@playwright/test';
import type { AuditFailure, AuditRole, FailureCategory, Severity, ViewportName } from './types';
import { OVERFLOW_TOLERANCE_PX, getOverflowMetrics } from './layout-checks';

export type PublicCriticalContext = {
  role: AuditRole;
  pageKey: string;
  pagePath: string;
  browser: string;
  viewport: ViewportName;
};

const SEARCH_PAGE_KEYS = new Set(['home', 'flights-search']);

function failure(
  category: FailureCategory,
  ctx: PublicCriticalContext,
  selector: string,
  issue: string,
  severity: Severity = 'High',
  suggestedFix?: string,
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
    suggestedFix: suggestedFix ?? 'Fix layout at this viewport; see UI responsive audit docs.',
  };
}

function isWithinViewport(
  box: { x: number; y: number; width: number; height: number },
  viewportWidth: number,
  viewportHeight: number,
  tolerance = OVERFLOW_TOLERANCE_PX,
): boolean {
  return (
    box.x >= -tolerance &&
    box.y >= -tolerance &&
    box.x + box.width <= viewportWidth + tolerance &&
    box.y + box.height <= viewportHeight + tolerance * 4
  );
}

export async function assertNoPageOverflow(page: Page, ctx: PublicCriticalContext): Promise<AuditFailure[]> {
  const metrics = await getOverflowMetrics(page);
  if (!metrics.hasOverflow) return [];

  return [
    failure(
      'horizontal_overflow',
      ctx,
      'html, body',
      `Horizontal overflow (scrollWidth ${Math.max(metrics.docScrollWidth, metrics.bodyScrollWidth)} > ${metrics.innerWidth})`,
      'High',
      'Apply overflow-x:clip on shell; ensure hero/search grids collapse on narrow viewports.',
    ),
  ];
}

export async function assertHeaderNavDoesNotOverlapMain(
  page: Page,
  ctx: PublicCriticalContext,
): Promise<AuditFailure[]> {
  const overlap = await page.evaluate(() => {
    const header = document.querySelector(
      'header, .ota-header, .navbar, [role="banner"]',
    ) as HTMLElement | null;
    const main = document.querySelector(
      'main, [role="main"], .ota-main, .page-body, .page-wrapper',
    ) as HTMLElement | null;
    if (!header || !main) return null;

    const headerBottom = header.getBoundingClientRect().bottom;
    if (headerBottom <= 0) return null;

    const anchors = main.querySelectorAll(
      '[data-hero-search], .ota-auth-card, form h1, form h2, main > h1, main > h2, form input, form select, form textarea, form button',
    );

    for (const el of Array.from(anchors)) {
      const html = el as HTMLElement;
      const style = window.getComputedStyle(html);
      if (style.display === 'none' || style.visibility === 'hidden') continue;
      const rect = html.getBoundingClientRect();
      if (rect.width < 24 || rect.height < 12) continue;

      const obscured = rect.top < headerBottom - 6 && rect.bottom > headerBottom + 4;
      const mostlyHidden = rect.top < headerBottom - 6 && rect.height > 0 && rect.bottom - headerBottom < rect.height * 0.45;

      if (obscured && mostlyHidden) {
        const name = (html.getAttribute('name') || html.id || html.tagName).toLowerCase();
        return {
          headerBottom,
          anchorTop: rect.top,
          selector: `${html.tagName.toLowerCase()}${name ? `[name="${name}"]` : ''}`,
        };
      }
    }

    return null;
  });

  if (!overlap) return [];

  return [
    failure(
      'header_footer_overlap',
      ctx,
      overlap.selector,
      `Header/navigation obscures primary content (anchor top=${Math.round(overlap.anchorTop)}, header bottom=${Math.round(overlap.headerBottom)})`,
      'High',
      'Add padding-top on main for fixed header height or reduce header overlap on mobile.',
    ),
  ];
}

export async function assertMainContentVisible(page: Page, ctx: PublicCriticalContext): Promise<AuditFailure[]> {
  const state = await page.evaluate(() => {
    const main = document.querySelector('main, [role="main"], .ota-main, .page-body, .page-wrapper');
    if (!main) return { visible: false, reason: 'missing' };
    const html = main as HTMLElement;
    const rect = html.getBoundingClientRect();
    const style = window.getComputedStyle(html);
    const visible =
      rect.width > 40 &&
      rect.height > 40 &&
      style.display !== 'none' &&
      style.visibility !== 'hidden' &&
      parseFloat(style.opacity || '1') > 0.05;
    return { visible, reason: visible ? 'ok' : 'hidden' };
  });

  if (state.visible) return [];

  return [
    failure(
      'landmark',
      ctx,
      'main',
      'Main content landmark not visible',
      'Critical',
      'Ensure main content renders and is not covered by overlays on this viewport.',
    ),
  ];
}

export async function assertPublicFormsFitViewport(
  page: Page,
  ctx: PublicCriticalContext,
): Promise<AuditFailure[]> {
  const offenders = await page.evaluate((tolerance) => {
    const vw = window.innerWidth;
    const bad: Array<{ selector: string; right: number }> = [];
    const roots = document.querySelectorAll(
      'form, [data-hero-search], [data-auth-form], .ota-auth-card form, .ota-support-form',
    );

    for (const root of Array.from(roots)) {
      for (const el of Array.from(root.querySelectorAll('input, select, textarea, button[type="submit"], button.ota-btn-primary'))) {
        const html = el as HTMLElement;
        const style = window.getComputedStyle(html);
        if (style.display === 'none' || style.visibility === 'hidden') continue;
        const rect = html.getBoundingClientRect();
        if (rect.width <= 0) continue;
        if (rect.right > vw + tolerance || rect.left < -tolerance) {
          const name = (html.getAttribute('name') || html.id || html.tagName).toLowerCase();
          bad.push({ selector: `${html.tagName.toLowerCase()}[name="${name}"]`, right: rect.right });
        }
        if (bad.length >= 6) return bad;
      }
    }

    return bad;
  }, OVERFLOW_TOLERANCE_PX);

  return offenders.map((o) =>
    failure(
      'form_field',
      ctx,
      o.selector,
      `Form control extends beyond viewport (right=${Math.round(o.right)}px)`,
      'Medium',
      'Use width:100% and collapse multi-column grids below 768px.',
    ),
  );
}

export async function assertButtonsClickableInViewport(
  page: Page,
  ctx: PublicCriticalContext,
): Promise<AuditFailure[]> {
  const clipped = await page.evaluate((tolerance) => {
    const vw = window.innerWidth;
    const bad: Array<{ selector: string; text: string }> = [];

    const selectors =
      'button, a.btn, .ota-btn-primary, .ota-hero-search-submit, input[type="submit"], [type="button"]';

    for (const el of Array.from(document.querySelectorAll(selectors))) {
      const html = el as HTMLElement;
      const text = (html.textContent || '').trim();
      if (!text || text.length > 80) continue;
      const style = window.getComputedStyle(html);
      if (style.display === 'none' || style.visibility === 'hidden' || parseFloat(style.opacity || '1') < 0.2) {
        continue;
      }
      const rect = html.getBoundingClientRect();
      if (rect.width <= 0 || rect.height <= 0) continue;

      const horizOff = rect.right > vw + tolerance || rect.left < -tolerance;
      if (horizOff) {
        bad.push({
          selector: html.tagName.toLowerCase() + (html.className ? `.${String(html.className).split(/\s+/)[0]}` : ''),
          text: text.slice(0, 40),
        });
      }
      if (bad.length >= 6) break;
    }

    return bad;
  }, OVERFLOW_TOLERANCE_PX);

  return clipped.map((item) =>
    failure(
      'clickable_actions',
      ctx,
      item.selector,
      `Button/action "${item.text}" is clipped horizontally outside viewport`,
      'High',
    ),
  );
}

export async function assertFixedStickyDoNotBlockForms(
  page: Page,
  ctx: PublicCriticalContext,
): Promise<AuditFailure[]> {
  const controls = page.locator(
    'form input:not([type="hidden"]):visible, form select:visible, form textarea:visible',
  );
  const count = await controls.count();
  const failures: AuditFailure[] = [];

  const viewport = page.viewportSize();

  for (let i = 0; i < Math.min(count, 8); i += 1) {
    const control = controls.nth(i);
    const box = await control.boundingBox();
    if (!box || !viewport || box.top < viewport.height * 0.55) {
      continue;
    }
    await control.scrollIntoViewIfNeeded().catch(() => undefined);

    const blocked = await control.evaluate((html) => {
      const el = html as HTMLElement;
      const form = el.closest('form, [data-hero-search]');
      const rect = el.getBoundingClientRect();
      if (rect.width < 8 || rect.height < 8) return null;

      const cx = rect.left + rect.width / 2;
      const cy = rect.top + Math.min(10, rect.height * 0.2);
      if (cx < 0 || cy < 0 || cx > window.innerWidth) return null;

      const stack = document.elementsFromPoint(cx, cy);
      const top = stack.find((node) => node !== el && !el.contains(node)) ?? null;
      if (!top) return null;

      const header = document.querySelector(
        'header, .ota-header, .ota-site-header, [role="banner"]',
      );
      if (
        (header && (header === top || header.contains(top))) ||
        top.closest('header, .ota-site-header, .ota-header, [role="banner"]')
      ) {
        return null;
      }
      if (form && form.contains(top)) return null;
      if (
        form &&
        el.closest('form') === form &&
        (top as HTMLElement).matches(
          'button[type="submit"], .ota-btn-primary, .ota-register-actions, .ota-register-actions *',
        )
      ) {
        return null;
      }

      let node: Element | null = top;
      while (node && node !== document.body) {
        const st = window.getComputedStyle(node);
        if (
          (st.position === 'fixed' || st.position === 'sticky') &&
          parseFloat(st.opacity || '1') > 0.35 &&
          st.pointerEvents !== 'none'
        ) {
          const blockerRect = (node as HTMLElement).getBoundingClientRect();
          if (blockerRect.top >= rect.bottom - 4) {
            break;
          }
          const coversProbe =
            blockerRect.left <= cx &&
            blockerRect.right >= cx &&
            blockerRect.top <= cy &&
            blockerRect.bottom >= cy;
          if (coversProbe && blockerRect.width > 24 && blockerRect.height > 24) {
            const name = (el.getAttribute('name') || el.id || 'control').toLowerCase();
            return {
              selector: `${el.tagName.toLowerCase()}[name="${name}"]`,
              blocker: node.className
                ? `${node.tagName.toLowerCase()}.${String(node.className).split(/\s+/)[0]}`
                : node.tagName.toLowerCase(),
            };
          }
        }
        node = node.parentElement;
      }

      return null;
    });

    if (blocked) {
      failures.push(
        failure(
          'navigation',
          ctx,
          blocked.selector,
          `Fixed/sticky element "${blocked.blocker}" blocks form control`,
          'High',
          'Lower z-index of sticky bars or add scroll padding so inputs are not covered.',
        ),
      );
    }
    if (failures.length >= 4) break;
  }

  return failures;
}

export async function assertPublicSearchFormUsable(
  page: Page,
  ctx: PublicCriticalContext,
): Promise<AuditFailure[]> {
  if (!SEARCH_PAGE_KEYS.has(ctx.pageKey)) return [];

  const widget = page.locator('[data-hero-search]').first();
  if ((await widget.count()) === 0) {
    return [
      failure(
        'landmark',
        ctx,
        '[data-hero-search]',
        'Public flight search widget not found',
        'High',
      ),
    ];
  }

  const failures: AuditFailure[] = [];
  await widget.scrollIntoViewIfNeeded().catch(() => undefined);

  try {
    await widget.waitFor({ state: 'visible', timeout: 8_000 });
  } catch {
    failures.push(
      failure('landmark', ctx, '[data-hero-search]', 'Public flight search widget not visible', 'High'),
    );
    return failures;
  }

  const submit = page.locator('.ota-hero-search-submit').first();
  if ((await submit.count()) === 0 || !(await submit.isVisible())) {
    failures.push(
      failure('clickable_actions', ctx, '.ota-hero-search-submit', 'Search submit button not visible', 'High'),
    );
  }

  const fromField = page
    .locator('[data-hero-search] [name="from_display"], [data-hero-search] [data-airport-display="from"]')
    .first();
  if ((await fromField.count()) > 0 && !(await fromField.isVisible())) {
    failures.push(failure('form_field', ctx, 'from_display', 'Origin field not visible in search form', 'Medium'));
  }

  return failures;
}

async function activePaxPicker(page: Page) {
  const pickers = page.locator('[data-hero-search] [data-pax-picker]');
  const count = await pickers.count();
  for (let i = 0; i < count; i += 1) {
    const candidate = pickers.nth(i);
    const adults = candidate.locator('[name="adults"]');
    if ((await adults.count()) > 0 && !(await adults.isDisabled())) {
      return candidate;
    }
  }
  return pickers.first();
}

export async function assertHeroPaxPanelInViewport(
  page: Page,
  ctx: PublicCriticalContext,
): Promise<AuditFailure[]> {
  if (!SEARCH_PAGE_KEYS.has(ctx.pageKey)) return [];

  const widget = page.locator('[data-hero-search]').first();
  if ((await widget.count()) === 0) return [];

  const picker = await activePaxPicker(page);
  if ((await picker.count()) === 0) return [];

  const trigger = picker.locator('.ota-hero-search-pax__trigger');
  if ((await trigger.count()) === 0) return [];

  await trigger.scrollIntoViewIfNeeded().catch(() => undefined);
  await trigger.click({ timeout: 8_000 }).catch(() => undefined);
  await page.waitForTimeout(250);
  let open = (await picker.getAttribute('open')) !== null;
  if (!open) {
    await trigger.click({ timeout: 8_000, force: true }).catch(() => undefined);
    await page.waitForTimeout(250);
    open = (await picker.getAttribute('open')) !== null;
  }
  if (!open) {
    return [
      failure(
        'dropdown_viewport',
        ctx,
        '.ota-hero-search-pax__trigger',
        'Travellers & Cabin dropdown did not open',
        'High',
      ),
    ];
  }

  const viewport = page.viewportSize();
  const panel = picker.locator('.ota-hero-search-pax__panel');
  const panelBox = await panel.boundingBox();
  if (!panelBox || !viewport) {
    return [
      failure('dropdown_viewport', ctx, '.ota-hero-search-pax__panel', 'Travellers panel has no bounding box', 'High'),
    ];
  }

  const panelFitsOrScrolls =
    panelBox.y + panelBox.height <= viewport.height + OVERFLOW_TOLERANCE_PX ||
    (await panel.evaluate((el) => {
      const style = window.getComputedStyle(el);
      const maxH = parseFloat(style.maxHeight || '0');
      const scrollable = style.overflowY === 'auto' || style.overflowY === 'scroll';
      return scrollable || maxH > 0;
    }));

  const panelHorizOk =
    panelBox.x >= -OVERFLOW_TOLERANCE_PX &&
    panelBox.x + panelBox.width <= viewport.width + OVERFLOW_TOLERANCE_PX;

  if (!panelHorizOk || !panelFitsOrScrolls) {
    return [
      failure(
        'dropdown_viewport',
        ctx,
        '.ota-hero-search-pax__panel',
        'Travellers & Cabin panel extends outside viewport',
        'High',
      ),
    ];
  }

  await page.keyboard.press('Escape').catch(() => undefined);
  return [];
}

export async function assertCalendarUsable(page: Page, ctx: PublicCriticalContext): Promise<AuditFailure[]> {
  if (!SEARCH_PAGE_KEYS.has(ctx.pageKey)) return [];

  const triggers = page.locator(
    '[data-hero-search] input[type="date"]:visible, [data-hero-search] [data-return-range-trigger="depart"]:visible, [data-hero-search] [data-depart-picker]:visible, [data-hero-search] button:has-text("Departure"):visible',
  );
  if ((await triggers.count()) === 0) return [];

  const trigger = triggers.first();
  await trigger.scrollIntoViewIfNeeded().catch(() => undefined);
  await trigger.click({ timeout: 8_000 }).catch(() => undefined);
  await page.waitForTimeout(350);

  const calendar = page
    .locator(
      '.flatpickr-calendar.open, .ota-return-range-picker--open, .ota-return-range-picker[role="dialog"]:not([hidden])',
    )
    .first();

  const viewport = page.viewportSize();
  const opened = await calendar.isVisible().catch(() => false);
  const triggerType = await trigger.getAttribute('type');
  const nativeOk = triggerType === 'date' && (await trigger.isVisible().catch(() => false));

  if (!opened && !nativeOk) {
    return [
      failure(
        'calendar',
        ctx,
        '[data-hero-search] date control',
        'Date picker did not open or become usable',
        'High',
      ),
    ];
  }

  const box = opened ? await calendar.boundingBox() : await trigger.boundingBox();
  if (box && viewport) {
    const horizOff =
      box.x < -OVERFLOW_TOLERANCE_PX || box.x + box.width > viewport.width + OVERFLOW_TOLERANCE_PX;
    if (horizOff) {
      return [
        failure(
          'calendar',
          ctx,
          '.flatpickr-calendar, .ota-return-range-picker',
          'Date picker extends horizontally outside viewport',
          'High',
        ),
      ];
    }
  }

  await page.keyboard.press('Escape').catch(() => undefined);
  return [];
}

export async function runPublicCriticalChecks(
  page: Page,
  ctx: PublicCriticalContext,
): Promise<AuditFailure[]> {
  await page.evaluate(() => window.scrollTo(0, 0));

  const checkFns = [
    assertNoPageOverflow,
    assertHeaderNavDoesNotOverlapMain,
    assertMainContentVisible,
    assertPublicSearchFormUsable,
    assertHeroPaxPanelInViewport,
    assertCalendarUsable,
    assertPublicFormsFitViewport,
    assertButtonsClickableInViewport,
    assertFixedStickyDoNotBlockForms,
  ];

  const failures: AuditFailure[] = [];
  for (const fn of checkFns) {
    failures.push(...(await fn(page, ctx)));
  }

  return failures;
}

export function filterPublicCriticalFailures(failures: AuditFailure[]): AuditFailure[] {
  const rank: Record<Severity, number> = { Critical: 0, High: 1, Medium: 2, Low: 3 };
  return failures.filter((f) => rank[f.severity] <= 2);
}
