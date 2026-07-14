import type { Locator, Page } from '@playwright/test';
import { futureDepartDate, futureReturnDate } from './constants';

export async function selectTripType(page: Page, trip: 'one_way' | 'round_trip' | 'multi_city'): Promise<void> {
  const tab = page.locator(`[data-jp-trip-tabs] button[data-jp-trip="${trip}"]`).first();
  await tab.click();
  await page.waitForTimeout(300);
}

export async function fillAirportField(page: Page, role: 'from' | 'to', code: string, display: string): Promise<void> {
  const displayInput = page.locator(`[data-jp-airport-display="${role}"]`).first();
  await displayInput.fill(display);
  const hidden = page.locator(`[data-jp-airport-code="${role}"]`).first();
  await hidden.evaluate((el, value) => {
    (el as HTMLInputElement).value = value;
    el.dispatchEvent(new Event('change', { bubbles: true }));
  }, code);
}

async function isVisible(locator: Locator): Promise<boolean> {
  if ((await locator.count()) === 0) {
    return false;
  }

  return locator.isVisible().catch(() => false);
}

async function setHiddenDateInput(locator: Locator, iso: string): Promise<void> {
  await locator.evaluate((el, value) => {
    const input = el as HTMLInputElement;
    input.value = value;
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));

    const field = input.closest('[data-jp-date-field]');
    const display = field?.querySelector('[data-jp-date-display]');
    if (display && value) {
      const parts = String(value).split('-');
      const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
      const day = parseInt(parts[2] ?? '1', 10);
      const month = months[parseInt(parts[1] ?? '1', 10) - 1] ?? '';
      display.textContent = `${day} ${month}`;
      display.classList.remove('is-placeholder');
    }

    field?.dispatchEvent(new CustomEvent('jp-date-change', { bubbles: true, detail: { value } }));
  }, iso);
}

async function fillVisibleOrHiddenDate(field: Locator, hiddenSelectors: string[], iso: string): Promise<boolean> {
  const visibleText = field
    .locator('input[type="text"][data-jp-date-display], .jp-date-field input:visible:not([type="hidden"])')
    .first();
  if (await isVisible(visibleText)) {
    await visibleText.fill(iso);
    return true;
  }

  for (const selector of hiddenSelectors) {
    const input = field.locator(selector).first();
    if ((await input.count()) === 0) {
      continue;
    }

    await setHiddenDateInput(input, iso);
    return true;
  }

  return false;
}

async function fillDepartDate(page: Page, iso: string): Promise<void> {
  const searchRoot = page.locator('[data-jp-search], [data-jp-flight-form]').first();
  const rangeField = searchRoot.locator('[data-jp-date-role="return_range"]').first();
  const oneWayField = searchRoot.locator('[data-jp-date-role="depart"]').first();

  const useRange = await isVisible(rangeField);
  const field = useRange ? rangeField : oneWayField;

  const filled = await fillVisibleOrHiddenDate(
    field,
    useRange ? ['input[data-jp-range-depart]', 'input[name="depart"]'] : ['input[data-jp-date-value]', 'input[name="depart"]'],
    iso,
  );

  if (filled) {
    return;
  }

  const namedDepart = page.locator('[data-jp-flight-form] input[name="depart"]').first();
  if ((await namedDepart.count()) > 0) {
    await setHiddenDateInput(namedDepart, iso);
  }
}

async function fillReturnDate(page: Page, iso: string): Promise<void> {
  const searchRoot = page.locator('[data-jp-search], [data-jp-flight-form]').first();
  const rangeField = searchRoot.locator('[data-jp-date-role="return_range"]').first();
  const returnField = searchRoot.locator('[data-jp-date-role="return"]').first();

  const useRange = await isVisible(rangeField);
  const field = useRange ? rangeField : returnField;

  const filled = await fillVisibleOrHiddenDate(
    field,
    useRange
      ? ['input[data-jp-range-return]', 'input[name="return_date"]']
      : ['input[data-jp-date-value]', 'input[name="return_date"]'],
    iso,
  );

  if (filled) {
    return;
  }

  const namedReturn = page.locator('[data-jp-flight-form] input[name="return_date"]').first();
  if ((await namedReturn.count()) > 0) {
    await setHiddenDateInput(namedReturn, iso);
  }
}

async function assertDepartSubmitValue(page: Page, iso: string): Promise<void> {
  const value = await page.locator('[data-jp-flight-form]').first().evaluate(() => {
    const input = document.querySelector('[data-jp-flight-form] input[name="depart"]') as HTMLInputElement | null;
    return input?.value ?? '';
  });

  if (value !== iso) {
    throw new Error(`Expected depart=${iso} before search submit, got "${value}"`);
  }
}

export async function fillOneWayDates(page: Page): Promise<void> {
  const depart = futureDepartDate();
  await fillDepartDate(page, depart);
  await assertDepartSubmitValue(page, depart);
}

export async function fillReturnDates(page: Page): Promise<void> {
  const depart = futureDepartDate();
  const ret = futureReturnDate();
  await fillDepartDate(page, depart);
  await fillReturnDate(page, ret);
  await assertDepartSubmitValue(page, depart);
}

export async function submitFlightSearch(page: Page): Promise<void> {
  await page.locator('[data-jp-flight-submit]').first().click();
  await page.waitForURL(/\/jetpk\/flights\/results/, { timeout: 120_000 });
}

export async function searchOneWayFromHome(page: Page): Promise<void> {
  await selectTripType(page, 'one_way');
  await fillAirportField(page, 'from', 'ISB', 'Islamabad');
  await fillAirportField(page, 'to', 'KHI', 'Karachi');
  await fillOneWayDates(page);
  await submitFlightSearch(page);
}

export async function searchReturnFromHome(page: Page): Promise<void> {
  await selectTripType(page, 'round_trip');
  await fillAirportField(page, 'from', 'ISB', 'Islamabad');
  await fillAirportField(page, 'to', 'KHI', 'Karachi');
  await fillReturnDates(page);
  await submitFlightSearch(page);
}

export async function fillGuestPassengerForm(page: Page): Promise<void> {
  const form = page.locator('#ota-checkout-passengers-form, form[data-checkout-passenger-form]').first();
  await form.waitFor({ state: 'visible', timeout: 30_000 });

  const firstPax = form.locator('.ota-passenger-card').first();
  if (await firstPax.count()) {
    const isOpen = await firstPax.evaluate((el) => (el as HTMLDetailsElement).open);
    if (!isOpen) {
      await firstPax.locator('summary').click();
    }
  }

  const setIfEmpty = async (selector: string, value: string) => {
    const field = form.locator(selector).first();
    if (!(await field.count())) return;
    const current = await field.inputValue().catch(() => '');
    if (!current) {
      await field.fill(value);
    }
  };

  await setIfEmpty('input[name*="[first_name]"]', 'Test');
  await setIfEmpty('input[name*="[last_name]"]', 'Traveller');
  await setIfEmpty('input[name*="[date_of_birth]"]', '1990-05-15');

  const gender = form.locator('select[name*="[gender]"]').first();
  if (await gender.count()) {
    await gender.selectOption('M').catch(() => undefined);
  }

  const nationality = form.locator('select[name*="[nationality]"]').first();
  if (await nationality.count()) {
    await nationality.selectOption({ index: 1 }).catch(() => undefined);
  }

  const passport = form.locator('input[name*="[passport_number]"]').first();
  if (await passport.count()) {
    await setIfEmpty('input[name*="[passport_number]"]', 'AB1234567');
    const issuer = form.locator('select[name*="[passport_issuing_country]"]').first();
    if (await issuer.count()) {
      await issuer.selectOption('PK').catch(() => undefined);
    }
    await setIfEmpty('input[name*="[passport_expiry_date]"]', '2030-12-31');
    await setIfEmpty('input[name*="[passport_issue_date]"]', '2020-01-01');
  }

  await setIfEmpty('#checkout-email, input[name="email"]', 'jetpk.audit@example.com');
  await setIfEmpty('#checkout-phone-number, input[name="phone_number"]', '3001234567');

  const country = form.locator('#checkout-country, select[name="country"]').first();
  if (await country.count()) {
    await country.selectOption({ index: 1 }).catch(() => undefined);
  }
}

export async function submitPassengerFormToReview(page: Page): Promise<void> {
  const form = page.locator('#ota-checkout-passengers-form, form[data-checkout-passenger-form]').first();
  await form.locator('button[type="submit"]').first().click();
  await page.waitForURL(/\/jetpk\/booking\/review/, { timeout: 90_000 });
}
