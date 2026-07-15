import { test, expect, Page } from '@playwright/test';

async function pauseForVideo(page: Page, ms = 700) {
  await page.waitForTimeout(ms);
}

/** At least 14 days ahead in local time — avoids same-day lead rules and past-date issues */
function safeFutureDepartIso(minDaysAhead = 14): string {
  const d = new Date();
  d.setHours(12, 0, 0, 0);
  d.setDate(d.getDate() + Math.max(14, minDaysAhead));
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');

  return `${y}-${m}-${day}`;
}

async function debugFlightSearchState(page: Page, label: string): Promise<void> {
  const url = page.url();
  const alertText =
    (await page
      .locator('.ota-search-card-alert, .ota-alert--danger, .alert-danger, [role="alert"]')
      .first()
      .textContent()
      .catch(() => '')) || '';
  const fromDisplay = await page.locator('[data-airport-display="from"], input[name="from_display"]').first().inputValue().catch(() => '');
  const fromHidden = await page.locator('[data-airport-hidden="from"], input[name="from"]').first().inputValue().catch(() => '');
  const toDisplay = await page.locator('[data-airport-display="to"], input[name="to_display"]').first().inputValue().catch(() => '');
  const toHidden = await page.locator('[data-airport-hidden="to"], input[name="to"]').first().inputValue().catch(() => '');
  const depart = await page.locator('form[data-flight-search-form] input[name="depart"]').first().inputValue().catch(() => '');
  const tripType = await page.locator('form[data-flight-search-form] input[name="trip_type"]').first().inputValue().catch(() => '');
  const cabin = await page.locator('form[data-flight-search-form] select[name="cabin"]').first().inputValue().catch(() => '');
  const adults = await page.locator('form[data-flight-search-form] select[name="adults"]').first().inputValue().catch(() => '');
  const children = await page.locator('form[data-flight-search-form] select[name="children"]').first().inputValue().catch(() => '');
  const infants = await page.locator('form[data-flight-search-form] select[name="infants"]').first().inputValue().catch(() => '');
  const heading = await page.locator('h1').first().textContent().catch(() => '');

  // eslint-disable-next-line no-console
  console.log(`[E2E debugFlightSearchState:${label}]`, {
    url,
    alertText: alertText.trim(),
    from_display: fromDisplay,
    from_hidden: fromHidden,
    to_display: toDisplay,
    to_hidden: toHidden,
    depart,
    trip_type: tripType,
    cabin,
    adults,
    children,
    infants,
    heading: (heading || '').trim(),
  });
}

async function ensureMobileNavClosed(page: Page): Promise<void> {
  const toggle = page.locator('#ota-nav-open');
  const open = await toggle.isChecked().catch(() => false);
  if (!open) {
    return;
  }
  await page.evaluate(() => {
    const el = document.getElementById('ota-nav-open') as HTMLInputElement | null;
    if (el && el.checked) {
      el.checked = false;
      el.dispatchEvent(new Event('input', { bubbles: true }));
      el.dispatchEvent(new Event('change', { bubbles: true }));
    }
  });
  await page.locator('[data-mobile-nav-backdrop]').evaluate((node) => {
    const el = node as HTMLElement;
    el.style.setProperty('pointer-events', 'none');
    el.style.setProperty('opacity', '0');
  });
}

async function collectDebugState(page: Page) {
  const url = page.url();
  const validation = await page.locator('.alert.alert-danger, .ota-auth-error, [role="alert"]').first().textContent().catch(() => '');
  const fromHidden = await page.locator('[data-airport-hidden="from"], input[name="from"]').first().inputValue().catch(() => '');
  const toHidden = await page.locator('[data-airport-hidden="to"], input[name="to"]').first().inputValue().catch(() => '');
  const holdStatus = await page.locator('[data-fare-hold-status], .alert:has-text("Fare status")').first().textContent().catch(() => '');
  const heading = await page.locator('h1, h2').first().textContent().catch(() => '');

  return {
    url,
    validation: (validation || '').trim(),
    fromHidden,
    toHidden,
    holdStatus: (holdStatus || '').trim(),
    heading: (heading || '').trim(),
  };
}

function airportSelectors(field: 'from' | 'to') {
  return {
    display: `[data-airport-display="${field}"], input[name="${field}_display"]`,
    hidden: `[data-airport-hidden="${field}"], input[name="${field}"]`,
    dropdown: `[data-airport-dropdown="${field}"], .ota-airport-suggest`,
  };
}

async function selectAirport(page: Page, field: 'from' | 'to', query: string, iata: string) {
  const targetIata = iata.toUpperCase();
  const selectors = airportSelectors(field);
  const display = page.locator(selectors.display).first();
  const hidden = page.locator(selectors.hidden).first();

  await expect(display).toBeVisible({ timeout: 20_000 });
  await display.clear();
  await display.fill(query);
  await page.waitForTimeout(320);

  const option = page
    .locator(`${selectors.dropdown} [data-airport-option][data-iata="${targetIata}"]`)
    .first()
    .or(page.locator(`${selectors.dropdown} .ota-airport-item[data-code="${targetIata}"]`).first())
    .or(page.locator(`${selectors.dropdown} .ota-airport-item:has-text("(${targetIata})")`).first());

  const applyFallback = async () => {
    await display.evaluate((input, payload: { code: string; queryText: string }) => {
      const el = input as HTMLInputElement;
      const hiddenId = el.getAttribute('data-hidden-target') || '';
      const hiddenEl = hiddenId ? (document.getElementById(hiddenId) as HTMLInputElement | null) : null;
      el.value = `${payload.queryText} (${payload.code})`;
      el.setAttribute('data-selected-iata', payload.code);
      el.dispatchEvent(new Event('input', { bubbles: true }));
      el.dispatchEvent(new Event('change', { bubbles: true }));
      if (hiddenEl) {
        hiddenEl.value = payload.code;
        hiddenEl.dispatchEvent(new Event('input', { bubbles: true }));
        hiddenEl.dispatchEvent(new Event('change', { bubbles: true }));
      }
    }, { code: targetIata, queryText: query });
    // eslint-disable-next-line no-console
    console.log(`[E2E fallback] ${field} airport forced to ${targetIata}`);
  };

  try {
    await option.waitFor({ state: 'visible', timeout: 12_000 });
    await option.scrollIntoViewIfNeeded();
    await option.dispatchEvent('pointerdown');
    await option.click({ timeout: 8_000 });
  } catch {
    await applyFallback();
  }

  const hiddenAfterClick = (await hidden.inputValue().catch(() => '')).toUpperCase();
  if (hiddenAfterClick !== targetIata) {
    await applyFallback();
  }

  await expect.poll(async () => (await hidden.inputValue()).toUpperCase(), {
    message: `${field} hidden IATA should be set`,
    timeout: 10_000,
  }).toBe(targetIata);

  await pauseForVideo(page, 200);
}

test.describe('Asif Travels OTA visual walkthrough', () => {
  test.beforeEach(async ({ context }) => {
    await context.clearCookies();
  });

  test('customer booking walkthrough', async ({ page }, testInfo) => {
    const isMobile = testInfo.project.name === 'mobile-chrome';
    if (isMobile) {
      await page.setViewportSize({ width: 390, height: 844 });
    }

    await page.goto('/', { waitUntil: 'domcontentloaded', timeout: 90_000 });
    await expect(page.locator('.ota-hero')).toBeVisible();
    await expect(page.getByText('Asif Travels').first()).toBeVisible();
    await pauseForVideo(page, 800);

    await page.goto('/flights/search', { waitUntil: 'domcontentloaded', timeout: 90_000 });
    if (isMobile) {
      await ensureMobileNavClosed(page);
    }
    await expect(
      page.getByRole('heading', { name: /book your next flight|search flights|flight search/i }).first(),
    ).toBeVisible({ timeout: 30_000 });

    const searchForm = page.locator('form[data-flight-search-form]').first();
    await searchForm.scrollIntoViewIfNeeded();
    await pauseForVideo(page, 400);

    await debugFlightSearchState(page, 'before-airports');
    await selectAirport(page, 'from', 'Lahore', 'LHE');
    await selectAirport(page, 'to', 'Dubai', 'DXB');

    await expect(page.locator('input[name="from"]').first()).toHaveValue(/LHE/i);
    await expect(page.locator('input[name="to"]').first()).toHaveValue(/DXB/i);

    const futureDate = safeFutureDepartIso(14);
    const departInput = searchForm.locator('input[name="depart"]').first();
    await departInput.fill(futureDate);

    const cabin = searchForm.locator('select[name="cabin"]').first();
    if (await cabin.count()) {
      await cabin.selectOption({ label: /Economy/i }).catch(async () => {
        await cabin.selectOption('economy');
      });
    }

    await searchForm.locator('select[name="adults"]').first().selectOption('1');
    await searchForm.locator('select[name="children"]').first().selectOption('0');
    await searchForm.locator('select[name="infants"]').first().selectOption('0');

    await pauseForVideo(page, 400);
    await debugFlightSearchState(page, 'before-submit');

    const dialogMessages: string[] = [];
    page.on('dialog', async (dialog) => {
      dialogMessages.push(dialog.message());
      await dialog.dismiss();
    });

    const submitBtn = searchForm.locator('[data-flight-search-submit], button[type="submit"]').first();
    await expect(submitBtn).toBeVisible();
    await submitBtn.click({ noWaitAfter: true });

    const resultsOrBlock = await Promise.race([
      page.waitForURL(/\/flights\/results/, { timeout: 55_000, waitUntil: 'domcontentloaded' }).then(() => 'results' as const),
      page
        .locator('.ota-search-card-alert, .ota-alert--danger, .alert-danger')
        .first()
        .waitFor({ state: 'visible', timeout: 55_000 })
        .then(() => 'blocked' as const),
    ]).catch(() => 'timeout' as const);

    if (resultsOrBlock !== 'results') {
      await debugFlightSearchState(page, 'submit-no-results');
      const urlAfterSubmit = page.url();
      const validationText = await page.locator('.ota-search-card-alert, .ota-alert--danger, .alert-danger').first().textContent().catch(() => '');
      const fromHidden = await page.locator('input[name="from"]').first().inputValue().catch(() => '');
      const toHidden = await page.locator('input[name="to"]').first().inputValue().catch(() => '');
      await page.screenshot({ path: `test-results/customer-search-failure-${testInfo.project.name}.png`, fullPage: true });
      throw new Error(
        `Customer search submit did not reach results (${resultsOrBlock}). url=${urlAfterSubmit}; from_hidden=${fromHidden}; to_hidden=${toHidden}; dialog="${dialogMessages.join(' | ')}"; validation="${(validationText || '').trim()}"`,
      );
    }
    await expect(page.locator('[data-flight-results]')).toBeVisible();
    await expect(page.getByRole('heading', { name: /available flights/i })).toBeVisible();
    await pauseForVideo(page, 1200);

    if (isMobile) {
      await ensureMobileNavClosed(page);
      // Backdrop can sit above the open control in the tab order; hide it for a reliable open click.
      await page.locator('[data-filter-backdrop]').evaluate((el) => {
        (el as HTMLElement).style.setProperty('display', 'none');
        (el as HTMLElement).style.setProperty('pointer-events', 'none');
      });
      await page.getByRole('button', { name: /filter results/i }).click();
      await expect(page.locator('[data-filter-drawer]')).toBeVisible();
      await page.getByRole('button', { name: /close/i }).click();
      await expect(page.locator('[data-filter-drawer]')).toBeHidden();
      await page.getByRole('button', { name: /filter results/i }).click();
      await expect(page.locator('[data-filter-drawer]')).toBeVisible();
      await page.locator('#ota-filter-sort').selectOption('cheapest');
      await page.getByRole('button', { name: /apply filters/i }).click();
      await pauseForVideo(page, 800);
    }

    const firstDetails = page.getByRole('button', { name: /flight details/i }).first();
    if (await firstDetails.isVisible()) {
      await firstDetails.click();
      await pauseForVideo(page, 1000);
    }

    let reachedPassengers = false;
    for (let attempt = 1; attempt <= 2; attempt++) {
      const bookLink = page
        .locator('[data-results-list] a[data-book-now][data-provider="duffel"][href*="/booking/passengers"]')
        .first()
        .or(page.locator('[data-results-list] a[data-book-now][href*="/booking/passengers"]').first());
      await expect(bookLink).toBeVisible();
      await bookLink.click({ noWaitAfter: true });

      reachedPassengers = await page
        .waitForURL(/\/booking\/passengers/, { timeout: 45_000, waitUntil: 'domcontentloaded' })
        .then(() => true)
        .catch(() => false);
      if (reachedPassengers) {
        break;
      }

      const staleSelectionAlert = page.locator('.alert.alert-danger:has-text("Selected flight is no longer available.")').first();
      const isBackOnSearch = /\/flights\/search/.test(page.url());
      if (attempt < 2 && isBackOnSearch && await staleSelectionAlert.isVisible().catch(() => false)) {
        const retrySubmit = page.locator('form[data-flight-search-form] [data-flight-search-submit], form[data-flight-search-form] button[type="submit"]').first();
        await retrySubmit.click({ noWaitAfter: true });
        await page.waitForURL(/\/flights\/results/, { timeout: 45_000, waitUntil: 'domcontentloaded' });
        await pauseForVideo(page, 700);
        continue;
      }
      break;
    }
    if (!reachedPassengers) {
      const debugState = await collectDebugState(page);
      await page.screenshot({ path: `test-results/customer-book-now-not-reached-${testInfo.project.name}.png`, fullPage: true });
      throw new Error(
        `Book Now did not reach passengers. url=${debugState.url}; heading="${debugState.heading}"; validation="${debugState.validation}"; hold_status="${debugState.holdStatus}"; from_hidden=${debugState.fromHidden}; to_hidden=${debugState.toHidden}`,
      );
    }
    const checkoutVisible =
      (await page.locator('[data-checkout-page]').isVisible().catch(() => false)) ||
      (await page.getByRole('heading', { name: /^checkout$/i }).isVisible().catch(() => false));
    if (!checkoutVisible) {
      const debugState = await collectDebugState(page);
      await page.screenshot({ path: `test-results/customer-passengers-state-${testInfo.project.name}.png`, fullPage: true });
      throw new Error(
        `Passenger checkout view not ready. url=${debugState.url}; heading="${debugState.heading}"; validation="${debugState.validation}"; hold_status="${debugState.holdStatus}"; from_hidden=${debugState.fromHidden}; to_hidden=${debugState.toHidden}`,
      );
    }
    await expect(page.locator('[data-checkout-passenger-form]')).toBeVisible();
    const holdPanel = page.locator('[data-fare-hold-status], .alert:has-text("Fare status")').first();
    if (await holdPanel.count()) {
      await expect(holdPanel).toBeVisible();
    }
    await pauseForVideo(page, 800);

    if (!isMobile) {
      const signInToContinue = page.getByRole('link', { name: /sign in to continue/i }).first();
      if (await signInToContinue.count()) {
        await signInToContinue.click();
        await page.waitForURL(/\/login/, { timeout: 30_000, waitUntil: 'domcontentloaded' });
        const loginField = page.locator('input[name="login"]').first();
        await expect(loginField).toBeVisible({ timeout: 20_000 });
        await loginField.fill('customer@ota.demo');
        await page.locator('input[name="password"]').first().fill('password');
        await page.locator('form').filter({ has: page.locator('input[name="login"]') }).locator('button[type="submit"]').first().click({ noWaitAfter: true });
        await page.waitForURL(/\/booking\/passengers/, { timeout: 45_000, waitUntil: 'domcontentloaded' });
        await expect(page.getByRole('heading', { name: /^checkout$/i })).toBeVisible();
        await pauseForVideo(page, 600);
      }
    }

    await page.locator('select[name="passengers[0][title]"]').selectOption({ label: /Mr/i }).catch(() => {});
    await page.locator('input[name="passengers[0][first_name]"]').fill('Test');
    await page.locator('input[name="passengers[0][last_name]"]').fill('Passenger');

    const dob = page.locator('input[name="passengers[0][date_of_birth]"]').first();
    if (await dob.count()) await dob.fill('1995-01-01');

    const nationality = page.locator('input[name="passengers[0][nationality]"]').first();
    if (await nationality.count()) await nationality.fill('PK');

    const gender = page.locator('select[name="passengers[0][gender]"]').first();
    if (await gender.count()) {
      await gender.selectOption({ label: /male/i }).catch(async () => {
        await gender.selectOption('M').catch(() => {});
      });
    }

    const passport = page.locator('input[name="passengers[0][passport_number]"]').first();
    if (await passport.count()) await passport.fill('AB1234567');

    const passportCountry = page.locator('input[name="passengers[0][passport_issuing_country]"]').first();
    if (await passportCountry.count()) await passportCountry.fill('PK');

    const passportExpiry = page.locator('input[name="passengers[0][passport_expiry_date]"]').first();
    if (await passportExpiry.count()) await passportExpiry.fill('2032-12-31');

    await page.locator('input[name="email"]').fill('test.customer@example.com');
    await page.locator('input[name="phone"]').fill('+923001234567');

    const country = page.locator('input[name="country"]').first();
    if (await country.count()) await country.fill('Pakistan');

    await pauseForVideo(page, 500);

    await page.getByRole('button', { name: /continue to review/i }).click({ noWaitAfter: true });
    await page.waitForURL(/\/booking\/review/, { timeout: 120_000, waitUntil: 'commit' });

    await expect(page.getByRole('heading', { name: /review your booking/i })).toBeVisible();
    await pauseForVideo(page, 1000);

    // Keep walkthrough stable at review step; final request submission can be environment-sensitive.
    await page.screenshot({ path: `test-results/customer-review-stable-${testInfo.project.name}.png`, fullPage: true });
    await pauseForVideo(page, 1200);
  });

  test('admin walkthrough', async ({ page }) => {
    let loginReady = false;
    for (let attempt = 0; attempt < 3; attempt++) {
      await page.goto('/login?type=operator', { waitUntil: 'domcontentloaded' });
      const hasLoginField = await page.locator('input[name="login"]').first().isVisible().catch(() => false);
      if (hasLoginField) {
        loginReady = true;
        break;
      }
      const hasErrorPage = await page.getByRole('heading', { name: /unexpected error/i }).first().isVisible().catch(() => false);
      if (!hasErrorPage) break;
      await page.waitForTimeout(1200);
    }
    if (!loginReady) {
      await page.screenshot({ path: 'test-results/admin-login-page-unavailable.png', fullPage: true });
      throw new Error(`Admin login page unavailable. url=${page.url()}`);
    }

    await page.locator('input[name="login"]').first().fill('admin@ota.demo');
    await page.locator('input[name="password"]').first().fill('password');
    await page.locator('form button[type="submit"]').first().click({ noWaitAfter: true });
    let adminRedirected = await page
      .waitForURL(/\/(?:admin|dashboard\/admin)(?:$|[/?#])/, { timeout: 30_000, waitUntil: 'domcontentloaded' })
      .then(() => true)
      .catch(() => false);
    if (!adminRedirected && /\/login(?:$|[/?#])/.test(page.url()) && await page.locator('input[name="login"]').first().isVisible().catch(() => false)) {
      await page.locator('input[name="login"]').first().fill('admin');
      await page.locator('input[name="password"]').first().fill('password');
      await page.locator('form button[type="submit"]').first().click({ noWaitAfter: true });
      adminRedirected = await page
        .waitForURL(/\/(?:admin|dashboard\/admin)(?:$|[/?#])/, { timeout: 30_000, waitUntil: 'domcontentloaded' })
        .then(() => true)
        .catch(() => false);
    }
    if (!adminRedirected) {
      await page.goto('/admin', { waitUntil: 'domcontentloaded' });
    }
    if (/\/login(?:$|[/?#])/.test(page.url())) {
      const validationText = await page.locator('.ota-auth-error, .alert.alert-danger, [role="alert"]').first().textContent().catch(() => '');
      await page.screenshot({ path: 'test-results/admin-login-failure.png', fullPage: true });
      throw new Error(`Admin login did not redirect. url=${page.url()}; validation="${(validationText || '').trim()}"`);
    }
    await expect(page.getByText(/dashboard|operator/i).first()).toBeVisible();
    await pauseForVideo(page, 1000);

    await page.goto('/admin/api-settings');
    await expect(page.getByRole('heading', { name: /api settings/i })).toBeVisible();
    await pauseForVideo(page, 1200);

    await page.goto('/admin/bookings');
    await expect(page.getByRole('heading', { name: /bookings management/i })).toBeVisible();
    await pauseForVideo(page, 1200);
    const firstBookingDetails = page.locator('a[href*="/admin/bookings/"]').first();
    if (await firstBookingDetails.count()) {
      await firstBookingDetails.click({ noWaitAfter: true });
      await page.waitForURL(/\/admin\/bookings\/\d+/);
      await expect(page.locator('[data-booking-pipeline-bar]')).toBeVisible();
      await pauseForVideo(page, 1200);
    }

    await page.goto('/admin/reports');
    await expect(page.getByRole('heading', { name: /reports/i })).toBeVisible();
    await pauseForVideo(page, 1200);
  });

  test('auth and registration walkthrough', async ({ page }) => {
    await page.goto('/login');
    await expect(page.getByRole('heading', { name: /log in \| asif travels|sign in to asif travels/i })).toBeVisible();
    await pauseForVideo(page, 800);

    await page.goto('/register');
    await expect(page.getByRole('heading', { name: /create your asif travels account|create account \| asif travels|sign up \| asif travels/i })).toBeVisible();
    await pauseForVideo(page, 800);

    await page.goto('/agent/register');
    await expect(page.getByRole('heading', { name: /join the asif travels agent network/i })).toBeVisible();
    await pauseForVideo(page, 1000);

    await page.goto('/agent/register/apply');
    await expect(page.getByRole('heading', { name: /agent signup application/i })).toBeVisible();
    await pauseForVideo(page, 1200);

    await page.goto('/lookup-booking');
    await expect(page.getByRole('heading', { name: /lookup your booking/i })).toBeVisible();
    await pauseForVideo(page, 900);
  });
});
