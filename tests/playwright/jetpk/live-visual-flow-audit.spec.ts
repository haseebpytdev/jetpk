import fs from 'node:fs';
import path from 'node:path';
import { test, expect } from '@playwright/test';
import {
  AUDIT_VIEWPORTS,
  CLIENT_PREFIX,
  GUEST_DASHBOARD_REDIRECTS,
  PRIMARY_VIEWPORT,
  PUBLIC_PAGES,
  oneWayResultsUrl,
  returnResultsUrl,
} from './helpers/constants';
import {
  createAuditState,
  getAuditState,
  persistAuditState,
  recomputeSummary,
  setAuditState,
} from './helpers/audit-state';
import {
  assertJetPkCardsPresent,
  assertJetPkResultsPage,
  collectSearchShellFingerprint,
  scanPageForLeaks,
} from './helpers/leak-scan';
import { captureScreenshot } from './helpers/screenshots';
import { searchOneWayFromHome, selectTripType, fillGuestPassengerForm, submitPassengerFormToReview } from './helpers/search';
import { AUDIT_OUTPUT_DIR } from './helpers/constants';

async function selectBrandedFareForCheckout(page: import('@playwright/test').Page): Promise<boolean> {
  await page.waitForSelector('.jp-flight-card, [data-flight-card]', { timeout: 90_000 }).catch(() => undefined);
  const card = page
    .locator('[data-flight-card][data-has-branded-fares], [data-flight-card][data-has-fare-choice], .jp-flight-card')
    .first();
  if (!(await card.count())) {
    return false;
  }

  const toggle = card.locator('[data-branded-fares-toggle]').first();
  if (await toggle.count()) {
    await toggle.click({ timeout: 15_000 }).catch(() => undefined);
    await page.waitForTimeout(600);
  }

  const fareOption = card.locator('[data-fare-option-card][data-fare-option-key]').first();
  if (await fareOption.count()) {
    await fareOption.click({ timeout: 20_000 });
    return true;
  }

  const priceBtn = card.locator('.jp-fare-action button, [data-select-offer], [data-book-now]').first();
  if (await priceBtn.count()) {
    await priceBtn.click({ timeout: 20_000 });
    return true;
  }

  return false;
}

test.describe.configure({ mode: 'serial' });

test.describe('JetPK live visual flow isolation audit (8D)', () => {
  test.beforeAll(() => {
    const out = path.join(process.cwd(), AUDIT_OUTPUT_DIR);
    fs.mkdirSync(path.join(out, 'screenshots'), { recursive: true });
    const state = createAuditState(AUDIT_VIEWPORTS.map((v) => `${v.width}x${v.height}`));
    setAuditState(state);
  });

  test('public pages + viewport leak scan', async ({ page }) => {
    const state = getAuditState();
    const notes: string[] = [];
    let fail = false;

    page.on('console', (msg) => {
      if (msg.type() === 'error') {
        state.consoleErrors.push(`${msg.text()} @ ${page.url()}`);
      }
    });
    page.on('response', (resp) => {
      if (resp.status() >= 400 && resp.url().includes('haseebasif.com')) {
        state.networkErrors.push(`${resp.status()} ${resp.url()}`);
      }
    });

    for (const vp of AUDIT_VIEWPORTS) {
      await page.setViewportSize({ width: vp.width, height: vp.height });
      const vpLabel = `${vp.width}x${vp.height}`;

      for (const pg of PUBLIC_PAGES) {
        const url = pg.path;
        state.testedUrls.push(url);
        const response = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 90_000 });
        const status = response?.status() ?? 0;
        if (status >= 400 && pg.key !== 'agent-register') {
          notes.push(`${pg.key} @ ${vpLabel}: HTTP ${status}`);
          if (vp.name === PRIMARY_VIEWPORT.name) fail = true;
          continue;
        }

        const leaks = await scanPageForLeaks(page, { pageKey: pg.key, viewport: vpLabel });
        state.leaks.push(...leaks);
        if (leaks.some((l) => l.severity === 'fail') && vp.name === PRIMARY_VIEWPORT.name) {
          fail = true;
        }

        if (vp.name === PRIMARY_VIEWPORT.name) {
          await captureScreenshot(page, `public-${pg.key}`, vpLabel);
        }

        if (pg.key === 'login' && vp.name === PRIMARY_VIEWPORT.name) {
          const formAction = await page.locator('form.jp-auth-form').first().getAttribute('action').catch(() => '');
          if (!formAction || !formAction.includes('/jetpk/login')) {
            state.leaks.push({
              page: 'login',
              viewport: vpLabel,
              kind: 'href',
              pattern: 'login form action',
              detail: `Login form action is ${formAction || '(missing)'}, expected /jetpk/login`,
              severity: 'fail',
            });
            fail = true;
          }
        }
      }
    }

    state.sections.publicPages = {
      status: fail ? 'fail' : 'pass',
      notes: notes.length ? notes : ['All public pages loaded on all viewports'],
    };
    persistAuditState();
  });

  test('guest dashboard redirects', async ({ page }) => {
    const state = getAuditState();
    await page.setViewportSize({ width: PRIMARY_VIEWPORT.width, height: PRIMARY_VIEWPORT.height });
    const notes: string[] = [];
    let fail = false;

    for (const entry of GUEST_DASHBOARD_REDIRECTS) {
      state.testedUrls.push(entry.path);
      await page.goto(entry.path, { waitUntil: 'domcontentloaded' });
      const finalUrl = page.url();
      if (!finalUrl.includes('/jetpk/login')) {
        fail = true;
        notes.push(`${entry.key}: expected /jetpk/login redirect, got ${finalUrl}`);
      } else {
        notes.push(`${entry.key}: redirects to /jetpk/login`);
      }
    }

    state.sections.guestRedirects = { status: fail ? 'fail' : 'pass', notes };
    persistAuditState();
  });

  test('search UI parity home vs results', async ({ page }) => {
    const state = getAuditState();
    await page.setViewportSize({ width: PRIMARY_VIEWPORT.width, height: PRIMARY_VIEWPORT.height });

    await page.goto(`${CLIENT_PREFIX}/home`, { waitUntil: 'networkidle', timeout: 90_000 });
    const homeFp = await collectSearchShellFingerprint(page);
    await captureScreenshot(page, 'home-search-box', `${PRIMARY_VIEWPORT.width}x${PRIMARY_VIEWPORT.height}`);

    await page.goto(oneWayResultsUrl(), { waitUntil: 'domcontentloaded', timeout: 120_000 });
    await page.waitForTimeout(5000);
    const resultsFp = await collectSearchShellFingerprint(page);
    await captureScreenshot(page, 'results-search-box', `${PRIMARY_VIEWPORT.width}x${PRIMARY_VIEWPORT.height}`);
    await page.locator('.jp-results-search-placement [data-jp-search], #jp-flight-search').first().scrollIntoViewIfNeeded().catch(() => undefined);
    await captureScreenshot(page, 'results-search-layout', `${PRIMARY_VIEWPORT.width}x${PRIMARY_VIEWPORT.height}`);

    const notes: string[] = [];
    let status: 'pass' | 'fail' | 'warn' = 'pass';

    if (!homeFp.exists || !resultsFp.exists) {
      status = 'fail';
      notes.push('search-shell missing on home or results');
    } else {
      if (homeFp.tabs !== resultsFp.tabs) {
        status = 'fail';
        notes.push(`trip tabs mismatch: home=${homeFp.tabs} results=${resultsFp.tabs}`);
      }
      if (homeFp.submitText !== resultsFp.submitText || homeFp.submitText !== 'Search') {
        status = 'fail';
        notes.push(`Search button text mismatch: home=${homeFp.submitText} results=${resultsFp.submitText}`);
      }
      if (!homeFp.hasFrom || !homeFp.hasTo || !resultsFp.hasFrom || !resultsFp.hasTo) {
        status = 'fail';
        notes.push('From/To airport fields missing on home or results');
      }
      notes.push('Home and results both use JetPK search-shell with matching tabs and Search label');
    }

    const submitBtn = page.locator('[data-jp-flight-submit]').first();
    if (await submitBtn.count()) {
      await submitBtn.click();
      const outline = await submitBtn.evaluate((el) => window.getComputedStyle(el).outlineColor);
      notes.push(`Search button post-click outline: ${outline}`);
    }

    state.sections.searchUiParity = { status, notes };
    persistAuditState();
  });

  test('one-way results flow', async ({ page }) => {
    const state = getAuditState();
    await page.setViewportSize({ width: PRIMARY_VIEWPORT.width, height: PRIMARY_VIEWPORT.height });
    state.testedUrls.push(oneWayResultsUrl());

    await page.goto(oneWayResultsUrl(), { waitUntil: 'domcontentloaded', timeout: 120_000 });
    await page.waitForTimeout(8000);

    const bodyIssue = await assertJetPkResultsPage(page);
    const leaks = await scanPageForLeaks(page, { pageKey: 'one-way-results', viewport: '1440x900' });
    state.leaks.push(...leaks);

    const cards = await assertJetPkCardsPresent(page);
    await captureScreenshot(page, 'one-way-results', '1440x900');

    const firstCard = page.locator('.jp-flight-card').first();
    if (await firstCard.count()) {
      await firstCard.scrollIntoViewIfNeeded();
      await captureScreenshot(page, 'one-way-result-card', '1440x900');
      await captureScreenshot(page, 'results-card-layout', '1440x900');
    }

    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2);
    const notes = [
      bodyIssue ? bodyIssue : 'body.jp-flights-results present',
      cards.detail,
      overflow ? 'horizontal overflow detected' : 'no horizontal overflow',
    ];

    let status: 'pass' | 'fail' | 'warn' = 'pass';
    if (bodyIssue || !cards.ok || overflow || leaks.some((l) => l.severity === 'fail')) {
      status = cards.detail.includes('empty') ? 'warn' : 'fail';
    }

    state.sections.oneWayFlow = { status, notes };
    persistAuditState();
  });

  test('return outbound + inbound flow', async ({ page }) => {
    const state = getAuditState();
    await page.setViewportSize({ width: PRIMARY_VIEWPORT.width, height: PRIMARY_VIEWPORT.height });
    state.testedUrls.push(returnResultsUrl());

    await page.goto(returnResultsUrl(), { waitUntil: 'domcontentloaded', timeout: 120_000 });
    await page.waitForTimeout(10000);

    const leaks = await scanPageForLeaks(page, { pageKey: 'return-outbound-results', viewport: '1440x900' });
    state.leaks.push(...leaks);
    await captureScreenshot(page, 'return-outbound-results', '1440x900');

    const outboundCard = page.locator('[data-split-flow-card="outbound"], .jp-flight-card[data-split-leg="outbound"]').first();
    const genericCard = page.locator('.jp-flight-card').first();
    const notes: string[] = [];
    let status: 'pass' | 'fail' | 'warn' = 'pass';

    if (await outboundCard.count()) {
      await outboundCard.scrollIntoViewIfNeeded();
      await captureScreenshot(page, 'return-outbound-card', '1440x900');
      notes.push('Return outbound split card rendered');
    } else if (await genericCard.count()) {
      notes.push('JetPK cards present (outbound split marker not found — may be combined list)');
    } else {
      status = 'warn';
      notes.push('No return outbound cards loaded (supplier empty or slow)');
    }

    const selectOutbound = page.locator('[data-split-leg="outbound"] button, [data-outbound-select], .jp-fare-action button').first();
    if (await selectOutbound.count()) {
      await selectOutbound.click({ timeout: 15_000 }).catch(() => undefined);
      await page.waitForTimeout(3000);

      if (page.url().includes('return-options') || await page.locator('[data-return-options-root]').count()) {
        state.testedUrls.push(page.url());
        await page.waitForTimeout(5000);
        await captureScreenshot(page, 'return-inbound-options', '1440x900');
        const inboundLeaks = await scanPageForLeaks(page, { pageKey: 'return-inbound-options', viewport: '1440x900' });
        state.leaks.push(...inboundLeaks);
        const inboundCards = await page.locator('.jp-flight-card').count();
        notes.push(inboundCards > 0 ? `${inboundCards} inbound JetPK card(s)` : 'Return options page loaded, cards pending/empty');
        if (inboundLeaks.some((l) => l.severity === 'fail')) status = 'fail';
      } else {
        notes.push('Outbound select did not navigate to return-options (inline return leg may be used)');
      }
    } else {
      notes.push('Outbound select control not found — results may still be loading');
      if (status === 'pass') status = 'warn';
    }

    state.sections.returnFlow = { status, notes };
    persistAuditState();
  });

  test('multi-city tab behavior', async ({ page }) => {
    const state = getAuditState();
    await page.setViewportSize({ width: PRIMARY_VIEWPORT.width, height: PRIMARY_VIEWPORT.height });
    await page.goto(`${CLIENT_PREFIX}/home`, { waitUntil: 'domcontentloaded' });

    await selectTripType(page, 'multi_city');
    const multiVisible = (await page.locator('[data-jp-multi-fields]:not([hidden])').count()) > 0;
    const simpleHidden = await page.locator('[data-jp-simple-fields]').evaluate((el) => (el as HTMLElement).hidden);

    const notes = [
      multiVisible ? 'Multi-city segment fields visible' : 'Multi-city fields not visible',
      simpleHidden ? 'Simple fields hidden for multi-city' : 'Simple fields still visible',
    ];

    await captureScreenshot(page, 'multi-city-tab', '1440x900');
    state.sections.multiCity = {
      status: multiVisible ? 'pass' : 'fail',
      notes,
    };
    persistAuditState();
  });

  test('branded fare tray carousel and layover tooltip', async ({ page }) => {
    const state = getAuditState();
    await page.setViewportSize({ width: PRIMARY_VIEWPORT.width, height: PRIMARY_VIEWPORT.height });
    await page.goto(oneWayResultsUrl(), { waitUntil: 'domcontentloaded', timeout: 120_000 });
    await page.waitForTimeout(10000);

    const card = page.locator('.jp-flight-card').first();
    const carouselNotes: string[] = [];
    const tooltipNotes: string[] = [];
    let carouselStatus: 'pass' | 'fail' | 'warn' = 'warn';
    let tooltipStatus: 'pass' | 'fail' | 'warn' = 'warn';

    if (await card.count()) {
      const fareToggle = card.locator('[data-branded-fares-toggle], [data-fare-options-toggle], .jp-flight-card__fare-toggle').first();
      if (await fareToggle.count()) {
        await fareToggle.click().catch(() => undefined);
        await page.waitForTimeout(800);
        await captureScreenshot(page, 'branded-fare-tray', '1440x900');

        const carousel = card.locator('.ota-branded-fares-carousel');
        const fareTiles = card.locator('.ota-branded-fares-panel__tile, .jp-branded-fare-card, [data-branded-fare-option]');
        const tileCount = await fareTiles.count();
        const hasCarousel = (await carousel.count()) > 0;

        if (tileCount >= 4 && hasCarousel) {
          carouselStatus = 'pass';
          carouselNotes.push(`${tileCount} branded fares with carousel nav`);
          await captureScreenshot(page, 'branded-fare-carousel', '1440x900');
        } else if (tileCount > 0) {
          carouselStatus = 'warn';
          carouselNotes.push(`${tileCount} branded fare(s); carousel not required below 4`);
        } else {
          carouselNotes.push('Branded fare tray opened but no fare tiles counted');
        }
      } else {
        carouselNotes.push('Fare toggle not found on first card');
      }

      const detailsBtn = card.locator('.jp-flight-card__details-btn, [data-flight-details]').first();
      if (await detailsBtn.count()) {
        await detailsBtn.click().catch(() => undefined);
        await page.waitForTimeout(600);
        const modal = page.locator('#jpFareModal, [data-fare-modal], .jp-fare-modal');
        if (await modal.count()) {
          await captureScreenshot(page, 'fare-details-modal', '1440x900');
          carouselNotes.push('Fare details modal opened');
        }
      }

      const layover = card.locator('[data-layover-tooltip], .jp-layover-chip, .ota-layover-summary').first();
      if (await layover.count()) {
        await layover.hover();
        await page.waitForTimeout(500);
        const tooltip = page.locator('[role="tooltip"], .jp-layover-tooltip, .ota-layover-tooltip').first();
        if (await tooltip.count()) {
          const text = await tooltip.innerText().catch(() => '');
          const lines = text.split('\n').filter(Boolean);
          tooltipStatus = lines.length >= 2 ? 'pass' : 'warn';
          tooltipNotes.push(`Layover tooltip visible (${lines.length} line(s))`);
          await captureScreenshot(page, 'layover-tooltip', '1440x900');
        } else {
          tooltipNotes.push('Layover element found but tooltip not visible on hover');
        }
      } else {
        tooltipNotes.push('No layover/stop chip on first card (direct flight or still loading)');
        tooltipStatus = 'warn';
      }
    } else {
      carouselNotes.push('No result card available for branded fare / layover checks');
      tooltipNotes.push('Skipped — no cards');
    }

    state.sections.brandedFareCarousel = { status: carouselStatus, notes: carouselNotes };
    state.sections.layoverTooltip = { status: tooltipStatus, notes: tooltipNotes };
    persistAuditState();
  });

  test('checkout visual flow (stop before payment)', async ({ page }) => {
    const state = getAuditState();
    await page.setViewportSize({ width: PRIMARY_VIEWPORT.width, height: PRIMARY_VIEWPORT.height });
    await page.goto(`${CLIENT_PREFIX}`, { waitUntil: 'domcontentloaded', timeout: 120_000 });
    await searchOneWayFromHome(page);

    const resultsRoot = page.locator('[data-results-root]').first();
    await resultsRoot.waitFor({ state: 'attached', timeout: 90_000 }).catch(() => undefined);
    const resultsUrlAttr = await resultsRoot.getAttribute('data-results-url').catch(() => '');
    const resultsSearchAttr = await resultsRoot.getAttribute('data-results-search-url').catch(() => '');
    const passengersUrlAttr = await resultsRoot.getAttribute('data-booking-passengers-url').catch(() => '');
    if (!resultsUrlAttr?.includes('/jetpk/flights/results') || !resultsSearchAttr?.includes('/jetpk/flights/results/search')) {
      state.leaks.push({
        page: 'results-url-config',
        viewport: `${PRIMARY_VIEWPORT.width}x${PRIMARY_VIEWPORT.height}`,
        kind: 'href',
        pattern: 'results runtime urls',
        detail: `data-results-url=${resultsUrlAttr || '(missing)'} data-results-search-url=${resultsSearchAttr || '(missing)'}`,
        severity: 'fail',
      });
    }
    if (!passengersUrlAttr?.includes('/jetpk/booking/passengers')) {
      state.leaks.push({
        page: 'results-url-config',
        viewport: `${PRIMARY_VIEWPORT.width}x${PRIMARY_VIEWPORT.height}`,
        kind: 'href',
        pattern: 'booking passengers url',
        detail: `data-booking-passengers-url=${passengersUrlAttr || '(missing)'}`,
        severity: 'fail',
      });
    }
    await page.waitForTimeout(10000);

    const notes: string[] = [];
    let status: 'pass' | 'fail' | 'warn' | 'blocked' = 'blocked';

    const selected = await selectBrandedFareForCheckout(page);
    if (!selected) {
      notes.push('No branded fare / selectable fare CTA — checkout visual proof blocked (no live results)');
      state.sections.checkoutVisual = { status, notes };
      persistAuditState();
      return;
    }

    await page.waitForURL(/\/jetpk\/booking\/passengers/, { timeout: 60_000 }).catch(() => undefined);

    if (!page.url().includes('/jetpk/booking/passengers')) {
      notes.push(`Did not reach passengers page — landed on ${page.url()}`);
      state.sections.checkoutVisual = { status: 'warn', notes };
      persistAuditState();
      return;
    }

    state.testedUrls.push(page.url());
    notes.push(`Passengers URL: ${page.url()}`);

    const pageTitleAttr = await page.title();
    if (/parwaaz/i.test(pageTitleAttr)) {
      notes.push(`Master-branded document title: ${pageTitleAttr}`);
    }
    notes.push(`Document title: ${pageTitleAttr}`);

    const progress = await page.locator('[data-jp-booking-progress]').count();
    const header = await page.locator('header#header, .header#header').count();
    const footer = await page.locator('footer.footer').count();
    const form = await page.locator('#ota-checkout-passengers-form, form[data-checkout-passenger-form]').count();
    const pageTitle = await page.locator('.ota-checkout-page-title').first().textContent().catch(() => '');
    const bodyText = await page.locator('body').innerText().catch(() => '');
    const supportText = await page.locator('.ota-checkout-wa').first().innerText().catch(() => '');

    if (progress !== 1) {
      notes.push(`Progress stepper count=${progress} (expected 1)`);
    }
    if (form === 0) {
      notes.push('Passenger/contact form not found');
    }
    if (header === 0 || footer === 0) {
      notes.push(`JetPK chrome missing (header=${header}, footer=${footer})`);
    }
    if (pageTitle && /parwaaz|yoursdomain|yd travel/i.test(pageTitle)) {
      notes.push(`Master-branded checkout heading: ${pageTitle.trim()}`);
    }
    const hasJetPkTitle =
      /passenger.*contact/i.test(pageTitle ?? '') ||
      /jetpakistan|jetpk/i.test(bodyText) ||
      /passenger.*contact/i.test(bodyText);
    if (!hasJetPkTitle) {
      notes.push('Missing JetPK/neutral passenger checkout title');
    }

    if (/\b123\b/.test(supportText)) {
      notes.push('Support card contains placeholder "123"');
    }

    const accountFields = page.locator('#checkout-inline-account-fields');
    const createAccount = page.locator('#checkout-create-account');
    if (await createAccount.count()) {
      const hiddenBefore = await accountFields.evaluate((el) => {
        const node = el as HTMLElement;
        return node.hidden || !node.classList.contains('is-open') || getComputedStyle(node).display === 'none';
      }).catch(() => false);
      if (!hiddenBefore) {
        notes.push('Password section visible before create-account checkbox');
      }
      await createAccount.check();
      await page.waitForTimeout(300);
      const visibleAfter = await accountFields.evaluate((el) => {
        const node = el as HTMLElement;
        return !node.hidden && node.classList.contains('is-open') && getComputedStyle(node).display !== 'none';
      }).catch(() => false);
      if (!visibleAfter) {
        notes.push('Password section not visible after create-account checkbox');
      }
      await createAccount.uncheck();
      await page.waitForTimeout(200);
    }

    const leaks = await scanPageForLeaks(page, { pageKey: 'checkout-passengers', viewport: '1440x900' });
    state.leaks.push(...leaks);
    await captureScreenshot(page, 'checkout-passengers-polished-1440x900', '1440x900');
    await captureScreenshot(page, 'checkout-passengers', '1440x900');
    await captureScreenshot(page, 'checkout-passengers-layout', '1440x900');
    notes.push(progress === 1 ? 'JetPK passenger page with single progress stepper' : 'Passengers page loaded (progress stepper mismatch)');

    let reachedReview = false;
    try {
      await fillGuestPassengerForm(page);
      await submitPassengerFormToReview(page);
      reachedReview = page.url().includes('/jetpk/booking/review');
      if (reachedReview) {
        state.testedUrls.push(page.url());
        notes.push(`Review URL: ${page.url()}`);
        const paymentCards = await page.locator('[data-jp-payment-options] .ota-method-card, .jp-checkout-card--payment .ota-method-card').count();
        if (paymentCards < 1) {
          notes.push('Review page missing styled payment cards');
        } else {
          notes.push(`${paymentCards} styled payment option card(s) on review page`);
        }
        await captureScreenshot(page, 'checkout-review-polished-1440x900', '1440x900');
        const reviewLeaks = await scanPageForLeaks(page, { pageKey: 'checkout-review', viewport: '1440x900' });
        state.leaks.push(...reviewLeaks);
      }
    } catch (err) {
      notes.push(`Guest form → review step: ${err instanceof Error ? err.message : String(err)}`);
    }

    const structuralFail = progress !== 1 || form === 0 || header === 0 || footer === 0;
    const brandFail =
      leaks.some((l) => l.severity === 'fail') ||
      /parwaaz|yoursdomain|yd travel/i.test(bodyText) ||
      (pageTitle ? /parwaaz|yoursdomain|yd travel/i.test(pageTitle) : false) ||
      /parwaaz/i.test(pageTitleAttr) ||
      /\b123\b/.test(supportText);
    const passwordFail = notes.some((n) => n.includes('Password section'));

    status = brandFail || structuralFail || passwordFail ? 'fail' : reachedReview ? 'pass' : 'warn';
    notes.push('Stopped before confirm booking — no booking mutation attempted');

    state.sections.checkoutVisual = { status, notes };
    persistAuditState();
  });

  test('results search UI parity with homepage', async ({ page }) => {
    const state = getAuditState();
    await page.setViewportSize({ width: PRIMARY_VIEWPORT.width, height: PRIMARY_VIEWPORT.height });

    await page.goto(`${CLIENT_PREFIX}`, { waitUntil: 'domcontentloaded', timeout: 120_000 });
    const homeShell = await collectSearchShellFingerprint(page);
    await captureScreenshot(page, 'home-search-shell', '1440x900');

    await page.goto(oneWayResultsUrl(), { waitUntil: 'domcontentloaded', timeout: 120_000 });
    await page.waitForSelector('.jp-results-search-placement [data-jp-search], #jp-flight-search', { timeout: 60_000 }).catch(() => undefined);
    const resultsShell = await collectSearchShellFingerprint(page);
    const searchCardVisible = (await page.locator('.jp-results-search-placement .jp-search-row .field, .jp-results-search-placement .jp-airport-field').count()) >= 2;
    await captureScreenshot(page, 'results-search-shell', '1440x900');
    await page.locator('.jp-results-search-placement [data-jp-search], #jp-flight-search').first().scrollIntoViewIfNeeded().catch(() => undefined);
    await captureScreenshot(page, 'results-search-layout', '1440x900');

    const notes: string[] = [];
    let status: 'pass' | 'fail' | 'warn' = 'pass';

    if (!homeShell.exists) {
      status = 'fail';
      notes.push('Home search shell missing ([data-jp-search] not found)');
    }
    if (!resultsShell.exists) {
      status = 'fail';
      notes.push('Results search shell missing ([data-jp-search] not found)');
    }

    const homeTokens = homeShell.classTokens ?? [];
    const resultsTokens = resultsShell.classTokens ?? [];

    if (homeShell.exists && resultsShell.exists) {
      const sharedClasses = ['search', 'jp-flight-form', 'jp-search-row', 'btn-search'];
      for (const cls of sharedClasses) {
        const homeHas = homeTokens.includes(cls);
        const resultsHas = resultsTokens.includes(cls);
        notes.push(`${cls}: home=${homeHas ? 'yes' : 'no'} results=${resultsHas ? 'yes' : 'no'}`);
        if (!homeHas || !resultsHas) {
          status = 'fail';
        }
      }

      if (!homeShell.searchButtonVisible || !resultsShell.searchButtonVisible) {
        status = 'fail';
        notes.push(
          `Search button visibility: home=${homeShell.searchButtonVisible ? 'yes' : 'no'} results=${resultsShell.searchButtonVisible ? 'yes' : 'no'}`,
        );
      } else {
        notes.push('Search button present on home and results search shells');
      }
    }

    if (!searchCardVisible && !resultsShell.fieldRowVisible) {
      status = 'fail';
      notes.push('Results search field row not visible');
    } else if (!searchCardVisible) {
      notes.push('Results field row locator mismatch (shell reports visible)');
    }

    const overflow = await page.evaluate(() => {
      const doc = document.documentElement;
      return doc.scrollWidth > doc.clientWidth + 2;
    });
    if (overflow) {
      status = 'fail';
      notes.push('Horizontal overflow detected on results search');
    }

    const blueRing = await page.evaluate(() => {
      const el = document.querySelector('.jp-results-search-placement .field input, .jp-results-search-placement .jp-airport-display');
      if (!el) return false;
      const before = getComputedStyle(el, ':focus-visible');
      return /rgb\(59,\s*130,\s*246\)|#3b82f6|outline.*blue/i.test(before.outlineColor || '');
    });
    if (blueRing) {
      notes.push('Blue focus ring detected on results search field');
      status = 'fail';
    }

    state.sections.resultsSearchParity = { status, notes };
    persistAuditState();
  });

  test('client no-fallback URL scan on JetPK public pages', async ({ page }) => {
    const state = getAuditState();
    const pages = [`${CLIENT_PREFIX}`, oneWayResultsUrl(), `${CLIENT_PREFIX}/login`];
    const notes: string[] = [];
    let status: 'pass' | 'fail' = 'pass';

    for (const url of pages) {
      await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 120_000 });
      const leaks = await scanPageForLeaks(page, { pageKey: url, viewport: '1440x900' });
      const rootLeaks = leaks.filter((l) => l.kind === 'href' && l.severity === 'fail');
      if (rootLeaks.length) {
        status = 'fail';
        notes.push(`${url}: ${rootLeaks.map((l) => l.pattern).join(', ')}`);
        state.leaks.push(...rootLeaks);
      }
    }

    if (status === 'pass') {
      notes.push('No forbidden root public URLs on home/results/login');
    }

    state.sections.clientNoFallback = { status, notes };
    persistAuditState();
  });

  test('checkout passenger layout proof', async ({ page }) => {
    const state = getAuditState();
    await page.setViewportSize({ width: PRIMARY_VIEWPORT.width, height: PRIMARY_VIEWPORT.height });
    await page.goto(`${CLIENT_PREFIX}`, { waitUntil: 'domcontentloaded', timeout: 120_000 });
    await searchOneWayFromHome(page);
    await page.waitForURL(/\/jetpk\/flights\/results/, { timeout: 120_000 }).catch(() => undefined);

    const selected = await selectBrandedFareForCheckout(page);
    if (!selected) {
      state.sections.checkoutLayout = { status: 'blocked', notes: ['No selectable fare for layout proof'] };
      persistAuditState();
      return;
    }

    await page.waitForURL(/\/jetpk\/booking\/passengers/, { timeout: 60_000 }).catch(() => undefined);
    const notes: string[] = [];
    let status: 'pass' | 'fail' = 'pass';

    if (!page.url().includes('/jetpk/booking/passengers')) {
      state.sections.checkoutLayout = { status: 'fail', notes: [`Landed on ${page.url()}`] };
      persistAuditState();
      return;
    }

    const progressCount = await page.locator('[data-jp-booking-progress]').count();
    if (progressCount !== 1) {
      status = 'fail';
      notes.push(`Progress stepper count=${progressCount} (expected 1)`);
    }

    const supportText = await page.locator('.ota-checkout-wa').first().innerText().catch(() => '');
    if (/\b123\b/.test(supportText)) {
      status = 'fail';
      notes.push('Support card contains placeholder "123"');
    }

    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 2);
    if (overflow) {
      status = 'fail';
      notes.push('Horizontal overflow on passenger page');
    }

    await captureScreenshot(page, 'checkout-passengers-layout', '1440x900');
    notes.push('Passenger layout screenshot captured');

    state.sections.checkoutLayout = { status, notes };
    persistAuditState();
  });

  test('authenticated header/dashboard (credential-gated)', async ({ page }) => {
    const state = getAuditState();
    const email = process.env.JETPK_LIVE_AUDIT_EMAIL ?? process.env.OTA_AUDIT_ADMIN_EMAIL ?? '';
    const password = process.env.JETPK_LIVE_AUDIT_PASSWORD ?? process.env.OTA_AUDIT_PASSWORD ?? '';

    if (!email || !password) {
      state.sections.headerProfileDashboard = {
        status: 'blocked',
        notes: ['credential-blocked: set JETPK_LIVE_AUDIT_EMAIL and JETPK_LIVE_AUDIT_PASSWORD for live dashboard proof'],
      };
      persistAuditState();
      return;
    }

    await page.setViewportSize({ width: PRIMARY_VIEWPORT.width, height: PRIMARY_VIEWPORT.height });
    await page.goto(`${CLIENT_PREFIX}/login`, { waitUntil: 'domcontentloaded' });
    await page.locator('input[name="login"], input[name="email"]').first().fill(email);
    await page.locator('input[name="password"]').fill(password);
    await page.locator('button[type="submit"]').first().click();
    await page.waitForURL((url) => !url.pathname.endsWith('/login'), { timeout: 45_000 }).catch(() => undefined);

    const notes: string[] = [];
    let status: 'pass' | 'fail' | 'blocked' = 'pass';

    if (page.url().includes('/login')) {
      status = 'blocked';
      notes.push('Login failed with provided credentials — credential-blocked');
    } else {
      await page.goto(`${CLIENT_PREFIX}/admin`, { waitUntil: 'domcontentloaded' }).catch(() => undefined);
      const guestLinks = await page.locator('header a:has-text("Sign in"), header a:has-text("Register")').count();
      const dropdown = await page.locator('[data-account-dropdown], .jp-account-menu, .ota-account-dropdown').count();
      notes.push(guestLinks === 0 ? 'No Sign in/Register while authenticated' : 'Sign in/Register still visible when authenticated');
      notes.push(dropdown > 0 ? 'Profile dropdown present' : 'Profile dropdown selector not found');
      await captureScreenshot(page, 'authenticated-admin-header', '1440x900');
      if (guestLinks > 0) status = 'fail';
    }

    state.sections.headerProfileDashboard = { status, notes };
    persistAuditState();
  });

  test.afterAll(() => {
    const state = getAuditState();
    recomputeSummary(state);
    persistAuditState();
  });
});
