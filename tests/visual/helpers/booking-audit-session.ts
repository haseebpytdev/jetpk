import type { Page } from '@playwright/test';

export const BOOKING_FIXTURE_OFFER_ID = 'fixture-offer-1';

/** GET path for guest passenger checkout (requires local offer cache or test doubles). */
export function bookingPassengersPath(): string {
  const d = new Date();
  d.setDate(d.getDate() + 21);
  const depart = d.toISOString().slice(0, 10);
  const q = new URLSearchParams({
    flight_id: BOOKING_FIXTURE_OFFER_ID,
    offer_id: BOOKING_FIXTURE_OFFER_ID,
    from: 'LHE',
    to: 'DXB',
    depart,
    trip_type: 'one_way',
    cabin: 'economy',
    adults: '1',
    children: '0',
    infants: '0',
  });

  return `/booking/passengers?${q}`;
}

/**
 * Attempts a safe guest POST to /booking/passengers for visual audit only.
 * Succeeds when the local app has a cached fixture offer (e.g. after PHPUnit doubles
 * or a prior local search). Does not call suppliers or payment.
 */
export async function prepareGuestBookingAuditSession(page: Page): Promise<boolean> {
  if (process.env.OTA_AUDIT_BOOKING_FIXTURE !== '1') {
    return false;
  }

  const path = bookingPassengersPath();
  await page.goto(path, { waitUntil: 'domcontentloaded', timeout: 60_000 });

  const form = page.locator('#ota-checkout-passengers-form');
  if ((await form.count()) === 0) {
    return false;
  }

  const token = await page.locator('input[name="_token"]').first().inputValue().catch(() => '');
  if (!token) {
    return false;
  }

  const depart = new URL(page.url()).searchParams.get('depart') ?? '';
  const expiry = new Date();
  expiry.setFullYear(expiry.getFullYear() + 7);

  const payload: Record<string, string> = {
    _token: token,
    flight_id: BOOKING_FIXTURE_OFFER_ID,
    offer_id: BOOKING_FIXTURE_OFFER_ID,
    from: 'LHE',
    to: 'DXB',
    depart,
    trip_type: 'one_way',
    cabin: 'economy',
    adults: '1',
    children: '0',
    infants: '0',
    title: 'Mr',
    first_name: 'Visual',
    last_name: 'Audit',
    dob: '1990-05-10',
    nationality: 'PK',
    gender: 'M',
    email: 'visual.audit@example.com',
    phone_country_code: '+92',
    phone_number: '3001112233',
    country: 'Pakistan',
    document_type: 'passport',
    create_account: '0',
    'passengers[0][passenger_type]': 'adult',
    'passengers[0][title]': 'Mr',
    'passengers[0][first_name]': 'Visual',
    'passengers[0][last_name]': 'Audit',
    'passengers[0][date_of_birth]': '1990-05-10',
    'passengers[0][gender]': 'M',
    'passengers[0][nationality]': 'PK',
    'passengers[0][document_type]': 'passport',
    'passengers[0][passport_number]': 'AB9988776',
    'passengers[0][passport_issuing_country]': 'PK',
    'passengers[0][passport_expiry_date]': expiry.toISOString().slice(0, 10),
    'passengers[0][passport_issue_date]': '2018-01-15',
    passport_number: 'AB9988776',
    passport_issuing_country: 'PK',
    passport_expiry_date: expiry.toISOString().slice(0, 10),
    passport_issue_date: '2018-01-15',
  };

  const response = await page.request.post(path, {
    form: payload,
    maxRedirects: 0,
    timeout: 60_000,
  });

  const location = response.headers().location ?? '';
  if (response.status() >= 300 && response.status() < 400 && location.includes('/booking/review')) {
    await page.goto('/booking/review', { waitUntil: 'domcontentloaded', timeout: 60_000 });
    return true;
  }

  if (page.url().includes('/booking/review')) {
    return true;
  }

  return false;
}
