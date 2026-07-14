export const LIVE_BASE_URL =
  (process.env.JETPK_LIVE_BASE_URL ?? process.env.PLAYWRIGHT_BASE_URL ?? 'https://jetpakistan.pk').replace(
    /\/$/,
    '',
  );

export const CLIENT_PREFIX = '/jetpk';

export const AUDIT_OUTPUT_DIR = 'storage/app/audits/jetpk-playwright-live';

export const SCREENSHOTS_DIR = `${AUDIT_OUTPUT_DIR}/screenshots`;

export const STATE_FILE = `${AUDIT_OUTPUT_DIR}/audit-state.json`;

export type ViewportDef = { name: string; width: number; height: number };

export const AUDIT_VIEWPORTS: ViewportDef[] = [
  { name: 'desktop1280', width: 1280, height: 720 },
  { name: 'desktop1366', width: 1366, height: 768 },
  { name: 'desktop1440', width: 1440, height: 900 },
  { name: 'desktop1536', width: 1536, height: 864 },
  { name: 'desktop1920', width: 1920, height: 1080 },
  { name: 'mobile390', width: 390, height: 844 },
  { name: 'tablet768', width: 768, height: 1024 },
];

export const PRIMARY_VIEWPORT = AUDIT_VIEWPORTS.find((v) => v.name === 'desktop1440')!;

export const PUBLIC_PAGES: { key: string; path: string }[] = [
  { key: 'root', path: '/jetpk' },
  { key: 'home', path: '/jetpk/home' },
  { key: 'about', path: '/jetpk/about-us' },
  { key: 'support', path: '/jetpk/support' },
  { key: 'login', path: '/jetpk/login' },
  { key: 'register', path: '/jetpk/register' },
  { key: 'forgot-password', path: '/jetpk/forgot-password' },
  { key: 'lookup-booking', path: '/jetpk/lookup-booking' },
  { key: 'groups-search', path: '/jetpk/groups/search' },
  { key: 'agent-register', path: '/jetpk/agent/register' },
];

export const GUEST_DASHBOARD_REDIRECTS: { key: string; path: string }[] = [
  { key: 'admin', path: '/jetpk/admin' },
  { key: 'staff', path: '/jetpk/staff' },
  { key: 'agent', path: '/jetpk/agent' },
  { key: 'customer', path: '/jetpk/customer' },
];

export const FORBIDDEN_TEXT_PATTERNS = [
  'Parwaaz Travels',
  'Parwaaz Travel',
  'Parwaaz',
  'YoursDomain',
  'YD Travel',
  'haseeb-master',
] as const;

export const FORBIDDEN_HREF_PATTERNS = [
  { pattern: /\/admin(?!\/)/i, label: 'bare /admin link' },
  { pattern: /\/login(?!\/)/i, label: 'bare /login link (non-jetpk)' },
  { pattern: /\/flights\/results/i, label: 'bare /flights/results link (non-jetpk)' },
] as const;

export const MASTER_CARD_SELECTORS = [
  '.ota-result-card:not(.jp-flight-card)',
  '.ota-return-split-card:not([class*="jp-"])',
  '[data-master-result-card]',
] as const;

export function futureDepartDate(daysAhead = 21): string {
  const d = new Date();
  d.setDate(d.getDate() + daysAhead);
  return d.toISOString().slice(0, 10);
}

export function futureReturnDate(daysAhead = 28): string {
  const d = new Date();
  d.setDate(d.getDate() + daysAhead);
  return d.toISOString().slice(0, 10);
}

export function oneWayResultsUrl(): string {
  const depart = futureDepartDate();
  const q = new URLSearchParams({
    trip_type: 'one_way',
    from: 'ISB',
    to: 'KHI',
    from_display: 'Islamabad',
    to_display: 'Karachi',
    depart,
    adults: '1',
    children: '0',
    infants: '0',
    cabin: 'economy',
  });
  return `${CLIENT_PREFIX}/flights/results?${q.toString()}`;
}

export function returnResultsUrl(): string {
  const depart = futureDepartDate();
  const ret = futureReturnDate();
  const q = new URLSearchParams({
    trip_type: 'round_trip',
    from: 'ISB',
    to: 'KHI',
    from_display: 'Islamabad',
    to_display: 'Karachi',
    depart,
    return_date: ret,
    adults: '1',
    children: '0',
    infants: '0',
    cabin: 'economy',
  });
  return `${CLIENT_PREFIX}/flights/results?${q.toString()}`;
}

