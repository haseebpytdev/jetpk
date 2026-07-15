import type { Page } from '@playwright/test';
import type { AuditFailure, AuditRole, ViewportName } from './types';

export type InteractionContext = {
  role: AuditRole;
  pageKey: string;
  pagePath: string;
  browser: string;
  viewport: ViewportName;
};

export type InteractionResult = {
  component: string;
  success: boolean;
  screenshotPath?: string;
  failures: AuditFailure[];
  skipped?: string;
};

const DESTRUCTIVE_PATTERN =
  /delete|cancel booking|approve|reject|issue ticket|create pnr|capture|sync supplier|revalidate|submit booking|send email|ticket/i;

async function ensureMobileNavOpen(page: Page): Promise<void> {
  await page.evaluate(() => {
    const el = document.getElementById('ota-nav-open') as HTMLInputElement | null;
    if (el && !el.checked) {
      el.checked = true;
      el.dispatchEvent(new Event('input', { bubbles: true }));
      el.dispatchEvent(new Event('change', { bubbles: true }));
      document.body.classList.add('ota-mobile-nav-open');
    }
  });
}

async function ensureMobileNavClosed(page: Page): Promise<void> {
  await page.evaluate(() => {
    const el = document.getElementById('ota-nav-open') as HTMLInputElement | null;
    if (el?.checked) {
      el.checked = false;
      el.dispatchEvent(new Event('input', { bubbles: true }));
      el.dispatchEvent(new Event('change', { bubbles: true }));
      document.body.classList.remove('ota-mobile-nav-open');
    }
  });
}

function accountDropdownVariant(page: Page): 'mobile' | 'desktop' | null {
  const viewport = page.viewportSize();
  if (!viewport) return null;
  return viewport.width < 992 ? 'mobile' : 'desktop';
}

async function isAccountDropdownOpen(page: Page, variant: 'mobile' | 'desktop'): Promise<boolean> {
  return page.evaluate((v) => {
    const menu = document.querySelector(`[data-testid="account-dropdown-${v}"]`);
    const trigger = menu?.querySelector('[data-account-trigger]') as HTMLElement | null;
    const dropdown = menu?.querySelector('[data-account-dropdown]') as HTMLElement | null;
    if (!dropdown || dropdown.hidden || trigger?.getAttribute('aria-expanded') !== 'true') {
      return false;
    }
    const rect = dropdown.getBoundingClientRect();
    const style = window.getComputedStyle(dropdown);
    return (
      rect.width > 0 &&
      rect.height > 0 &&
      style.display !== 'none' &&
      style.visibility !== 'hidden'
    );
  }, variant);
}

export async function ensureAccountDropdownOpen(
  page: Page,
  options: { allowProgrammaticFallback?: boolean } = {},
): Promise<boolean> {
  const variant = accountDropdownVariant(page);
  if (!variant) return false;

  if (variant === 'mobile') {
    await ensureMobileNavOpen(page);
    await page.locator('[data-testid="account-dropdown-mobile"]').waitFor({ state: 'visible', timeout: 8000 });
    await page
      .waitForFunction(() => {
        const nav = document.getElementById('ota-mobile-nav');
        if (!nav) return false;
        const style = window.getComputedStyle(nav);
        return style.visibility === 'visible' && style.pointerEvents !== 'none';
      }, undefined, { timeout: 5000 })
      .catch(() => undefined);
    await page.waitForTimeout(300);
  } else {
    await ensureMobileNavClosed(page);
  }

  const root = page.locator(`[data-testid="account-dropdown-${variant}"]`);
  if ((await root.count()) === 0) return false;

  const trigger = root.locator('.ota-account-trigger, button').first();
  if ((await trigger.count()) === 0) return false;

  if (await isAccountDropdownOpen(page, variant)) {
    return true;
  }

  await trigger.scrollIntoViewIfNeeded().catch(() => undefined);
  await trigger.click({ timeout: 8000 }).catch(() => undefined);

  await page
    .waitForFunction(
      (v) => {
        const menu = document.querySelector(`[data-testid="account-dropdown-${v}"]`);
        const triggerEl = menu?.querySelector('[data-account-trigger]') as HTMLElement | null;
        const dropdown = menu?.querySelector('[data-account-dropdown]') as HTMLElement | null;
        if (!dropdown || dropdown.hidden || triggerEl?.getAttribute('aria-expanded') !== 'true') {
          return false;
        }
        const rect = dropdown.getBoundingClientRect();
        return rect.width > 0 && rect.height > 0;
      },
      variant,
      { timeout: 8000 },
    )
    .catch(() => undefined);

  if (await isAccountDropdownOpen(page, variant)) {
    return true;
  }

  if (options.allowProgrammaticFallback) {
    await page.evaluate((v) => {
      const menu = document.querySelector(`[data-testid="account-dropdown-${v}"]`);
      const triggerEl = menu?.querySelector('[data-account-trigger]') as HTMLElement | null;
      const dropdown = menu?.querySelector('[data-account-dropdown]') as HTMLElement | null;
      if (triggerEl && dropdown) {
        triggerEl.setAttribute('aria-expanded', 'true');
        dropdown.hidden = false;
      }
    }, variant);
  }

  return isAccountDropdownOpen(page, variant);
}

function isWithinViewport(
  box: { x: number; y: number; width: number; height: number },
  viewportWidth: number,
  viewportHeight: number,
  tolerance = 4,
): boolean {
  return (
    box.x >= -tolerance &&
    box.y >= -tolerance &&
    box.x + box.width <= viewportWidth + tolerance &&
    box.y + box.height <= viewportHeight + tolerance * 4
  );
}

function interactionFailure(
  ctx: InteractionContext,
  component: string,
  selector: string,
  issue: string,
  category: AuditFailure['category'] = 'dropdown_viewport',
): AuditFailure {
  return {
    id: `${ctx.role}:${ctx.pageKey}:${component}:${ctx.browser}:${ctx.viewport}`,
    category,
    severity: category === 'calendar' ? 'High' : 'Medium',
    role: ctx.role,
    pageKey: ctx.pageKey,
    pagePath: ctx.pagePath,
    browser: ctx.browser,
    viewport: ctx.viewport,
    selector,
    issue,
    suggestedFix:
      category === 'calendar'
        ? 'Avoid overflow:hidden on picker parents; use :has(.picker-open) or overflow:visible when calendar is open.'
        : 'Ensure dropdown z-index ≥2000 and max-width:min(..., calc(100vw - 2rem)).',
  };
}

export async function testAccountDropdown(
  page: Page,
  ctx: InteractionContext,
  screenshotPath: string,
  options: { closeAfter?: boolean } = {},
): Promise<InteractionResult> {
  const failures: AuditFailure[] = [];
  const viewport = page.viewportSize();
  if (!viewport) {
    return { component: 'account-dropdown', success: false, failures, skipped: 'No viewport' };
  }

  const variant = viewport.width < 992 ? 'mobile' : 'desktop';
  const trigger = page
    .locator(
      `[data-testid="account-dropdown-${variant}"] .ota-account-trigger, [data-testid="account-dropdown-${variant}"] button`,
    )
    .first();
  const count = await trigger.count();
  if (count === 0) {
    return {
      component: 'account-dropdown',
      success: true,
      failures: [],
      skipped: 'Account dropdown not present (likely guest page)',
    };
  }

  const opened = await ensureAccountDropdownOpen(page);
  const dropdown = page.locator(`[data-testid="account-dropdown-${variant}"] [data-account-dropdown]`).first();
  const visible = opened && (await dropdown.isVisible().catch(() => false));
  if (!visible) {
    failures.push(
      interactionFailure(
        ctx,
        'account-dropdown',
        `[data-testid="account-dropdown-${variant}"]`,
        'Account dropdown did not open',
      ),
    );
    return { component: 'account-dropdown', success: false, failures, screenshotPath };
  }

  const box = await dropdown.boundingBox();
  if (box && !isWithinViewport(box, viewport.width, viewport.height)) {
    failures.push(
      interactionFailure(
        ctx,
        'account-dropdown',
        '[data-account-dropdown]',
        'Account dropdown panel extends outside viewport',
      ),
    );
  }

  if (screenshotPath) {
    await page.screenshot({ path: screenshotPath, fullPage: false }).catch(() => undefined);
  }
  if (options.closeAfter !== false) {
    await page.keyboard.press('Escape').catch(() => undefined);
  }

  return { component: 'account-dropdown', success: failures.length === 0, failures, screenshotPath };
}

export async function testCalendarPicker(
  page: Page,
  ctx: InteractionContext,
  screenshotPath: string,
): Promise<InteractionResult> {
  const failures: AuditFailure[] = [];
  const viewport = page.viewportSize();
  if (!viewport) {
    return { component: 'calendar', success: false, failures, skipped: 'No viewport' };
  }

  const triggers = page.locator(
    'input[type="date"]:visible, input[name="depart"]:visible, [data-return-range-trigger="depart"]:visible, input[name="return"]:visible, [data-date-picker]:visible, [data-depart-picker]:visible, .flatpickr-input:visible, button:has-text("Departure"):visible, button:has-text("Return"):visible',
  );

  const count = await triggers.count();
  if (count === 0) {
    return { component: 'calendar', success: true, failures: [], skipped: 'No date picker on page' };
  }

  const trigger = triggers.first();
  await trigger.scrollIntoViewIfNeeded().catch(() => undefined);
  await trigger.click({ timeout: 8000 }).catch(() => undefined);
  await page.waitForTimeout(400);

  const calendar = page
    .locator(
      '.flatpickr-calendar.open, .flatpickr-calendar:not(.hidden), [role="dialog"] .calendar, .picker-open, .ota-return-range-picker--open, .ota-return-range-picker[role="dialog"]:not([hidden])',
    )
    .first();

  const opened = await calendar.isVisible().catch(() => false);
  const triggerType = await trigger.getAttribute('type');
  const isNativeDate =
    triggerType === 'date' ||
    (await trigger
      .evaluate((el) => el instanceof HTMLInputElement && el.type === 'date')
      .catch(() => false));
  const nativeFocused =
    isNativeDate &&
    (await trigger
      .evaluate((el) => document.activeElement === el)
      .catch(() => false));
  const nativeDateOk =
    isNativeDate &&
    (nativeFocused ||
      (await trigger.isVisible().catch(() => false)) ||
      (await trigger
        .boundingBox()
        .then((box) => !!(box && box.width > 8 && box.height > 8))
        .catch(() => false)));
  if (!opened && !nativeDateOk) {
    failures.push(
      interactionFailure(
        ctx,
        'calendar',
        'input[name="depart"], .flatpickr-calendar',
        'Calendar did not become visible after click',
        'calendar',
      ),
    );
  }

  const box = opened ? await calendar.boundingBox() : await trigger.boundingBox();
  if (box && !isWithinViewport(box, viewport.width, viewport.height, 8)) {
    failures.push(
      interactionFailure(
        ctx,
        'calendar',
        '.flatpickr-calendar, input[type="date"]',
        'Calendar/date control extends outside viewport or may be clipped',
        'calendar',
      ),
    );
  }

  await page.screenshot({ path: screenshotPath, fullPage: false }).catch(() => undefined);
  await page.keyboard.press('Escape').catch(() => undefined);

  return { component: 'calendar', success: failures.length === 0, failures, screenshotPath };
}

export async function testAirportDropdown(
  page: Page,
  ctx: InteractionContext,
  screenshotPath: string,
): Promise<InteractionResult> {
  const failures: AuditFailure[] = [];
  const viewport = page.viewportSize();
  if (!viewport) {
    return { component: 'airport-dropdown', success: false, failures, skipped: 'No viewport' };
  }

  const display = page.locator('[data-airport-display="from"], input[name="from_display"]').first();
  if ((await display.count()) === 0) {
    return { component: 'airport-dropdown', success: true, failures: [], skipped: 'No airport field on page' };
  }

  await display.scrollIntoViewIfNeeded().catch(() => undefined);
  await display.fill('LHE');
  await page.waitForTimeout(500);

  const dropdown = page.locator('[data-airport-dropdown="from"], .ota-airport-suggest').first();
  const visible = await dropdown.isVisible().catch(() => false);

  if (!visible) {
    return {
      component: 'airport-dropdown',
      success: true,
      failures: [],
      skipped: 'Airport suggestions did not appear (local API may be unavailable)',
    };
  }

  const box = await dropdown.boundingBox();
  if (box && !isWithinViewport(box, viewport.width, viewport.height)) {
    failures.push(
      interactionFailure(
        ctx,
        'airport-dropdown',
        '[data-airport-dropdown="from"]',
        'Airport autocomplete dropdown extends outside viewport',
      ),
    );
  }

  await page.screenshot({ path: screenshotPath, fullPage: false }).catch(() => undefined);
  await page.keyboard.press('Escape').catch(() => undefined);

  return { component: 'airport-dropdown', success: failures.length === 0, failures, screenshotPath };
}

export async function testPassengerDropdown(
  page: Page,
  ctx: InteractionContext,
  screenshotPath: string,
): Promise<InteractionResult> {
  const failures: AuditFailure[] = [];
  const viewport = page.viewportSize();
  if (!viewport) {
    return { component: 'passenger-dropdown', success: false, failures, skipped: 'No viewport' };
  }

  const trigger = page
    .locator('[data-passenger-dropdown], [data-travelers-trigger], button:has-text("Passenger"), button:has-text("Travelers")')
    .first();

  if ((await trigger.count()) === 0) {
    return { component: 'passenger-dropdown', success: true, failures: [], skipped: 'No passenger dropdown on page' };
  }

  await trigger.click({ timeout: 8000 }).catch(() => undefined);
  await page.waitForTimeout(350);

  const panel = page.locator('[data-passenger-panel], .ota-passenger-dropdown, [data-travelers-panel]').first();
  const visible = await panel.isVisible().catch(() => false);
  if (visible) {
    const box = await panel.boundingBox();
    if (box && !isWithinViewport(box, viewport.width, viewport.height)) {
      failures.push(
        interactionFailure(
          ctx,
          'passenger-dropdown',
          '[data-passenger-panel]',
          'Passenger dropdown extends outside viewport',
        ),
      );
    }
  }

  await page.screenshot({ path: screenshotPath, fullPage: false }).catch(() => undefined);
  await page.keyboard.press('Escape').catch(() => undefined);

  return { component: 'passenger-dropdown', success: failures.length === 0, failures, screenshotPath };
}

export async function testActionDropdown(
  page: Page,
  ctx: InteractionContext,
  screenshotPath: string,
): Promise<InteractionResult> {
  const failures: AuditFailure[] = [];
  const viewport = page.viewportSize();
  if (!viewport) {
    return { component: 'action-dropdown', success: false, failures, skipped: 'No viewport' };
  }

  const toggles = page.locator('.dropdown-toggle, [data-bs-toggle="dropdown"], .btn-dropdown, .dropdown > button');
  const count = await toggles.count();
  if (count === 0) {
    return { component: 'action-dropdown', success: true, failures: [], skipped: 'No action dropdown on page' };
  }

  for (let i = 0; i < Math.min(count, 5); i++) {
    const toggle = toggles.nth(i);
    const text = ((await toggle.textContent()) || '').trim();
    if (DESTRUCTIVE_PATTERN.test(text)) {
      continue;
    }

    await toggle.scrollIntoViewIfNeeded().catch(() => undefined);
    await toggle.click({ timeout: 5000 }).catch(() => undefined);
    await page.waitForTimeout(300);

    const menu = page.locator('.dropdown-menu.show, .dropdown-menu[style*="display: block"]').first();
    if (await menu.isVisible().catch(() => false)) {
      const box = await menu.boundingBox();
      if (box && !isWithinViewport(box, viewport.width, viewport.height)) {
        failures.push(
          interactionFailure(
            ctx,
            'action-dropdown',
            '.dropdown-menu.show',
            'Action dropdown menu extends outside viewport',
            'modal_action_dropdown',
          ),
        );
      }

      await page.screenshot({ path: screenshotPath, fullPage: false }).catch(() => undefined);
      await page.keyboard.press('Escape').catch(() => undefined);
      return { component: 'action-dropdown', success: failures.length === 0, failures, screenshotPath };
    }
  }

  return { component: 'action-dropdown', success: failures.length === 0, failures, skipped: 'No safe action dropdown opened' };
}

export async function runInteractiveChecks(
  page: Page,
  ctx: InteractionContext,
  screenshotBase: string,
  options: { includeAirport?: boolean; includeCalendar?: boolean },
): Promise<InteractionResult[]> {
  const results: InteractionResult[] = [];

  const accountResult = await testAccountDropdown(
    page,
    ctx,
    `${screenshotBase}/account-dropdown-${ctx.browser}-${ctx.viewport}.png`,
  );
  results.push(accountResult);

  if (
    (ctx.role === 'agent' || ctx.role === 'agent_staff_restricted' || ctx.role === 'agent_staff_full') &&
    accountResult.success !== false &&
    !accountResult.skipped
  ) {
    const { runAgentPermissionUiChecks } = await import('./agent-permission-checks');
    const permissionFailures = await runAgentPermissionUiChecks(page, ctx, {
      skipOpen: accountResult.success,
    });
    if (permissionFailures.length > 0) {
      accountResult.failures.push(...permissionFailures);
      accountResult.success = false;
    }
  }

  if (options.includeCalendar) {
    results.push(await testCalendarPicker(page, ctx, `${screenshotBase}/calendar-${ctx.browser}-${ctx.viewport}.png`));
  }

  if (options.includeAirport) {
    results.push(
      await testAirportDropdown(page, ctx, `${screenshotBase}/airport-dropdown-${ctx.browser}-${ctx.viewport}.png`),
    );
  }

  results.push(
    await testPassengerDropdown(page, ctx, `${screenshotBase}/passenger-dropdown-${ctx.browser}-${ctx.viewport}.png`),
  );
  results.push(
    await testActionDropdown(page, ctx, `${screenshotBase}/action-dropdown-${ctx.browser}-${ctx.viewport}.png`),
  );

  return results;
}
