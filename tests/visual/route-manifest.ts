import type { AuditPageDef, AuditRole, RoleManifest, SkippedPage } from './helpers/types';
import { bookingPassengersPath } from './helpers/booking-audit-session';

function futureDepart(daysAhead = 21): string {
  const d = new Date();
  d.setDate(d.getDate() + daysAhead);
  return d.toISOString().slice(0, 10);
}

const flightResultsQuery = `from=LHE&to=DXB&depart=${futureDepart()}&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0`;

export const PUBLIC_PAGES: AuditPageDef[] = [
  { key: 'home', path: '/', label: 'Home', highRisk: true, interactive: true },
  { key: 'login', path: '/login', label: 'Login' },
  { key: 'register', path: '/register', label: 'Register' },
  { key: 'forgot-password', path: '/forgot-password', label: 'Forgot password' },
  { key: 'lookup-booking', path: '/lookup-booking', label: 'Booking lookup' },
  {
    key: 'booking-passengers',
    path: bookingPassengersPath(),
    label: 'Booking passengers',
    highRisk: true,
  },
  {
    key: 'booking-review',
    path: '/booking/review',
    label: 'Booking review',
    highRisk: true,
    skipReason:
      'Requires active booking session — set OTA_AUDIT_BOOKING_FIXTURE=1 with a local fixture offer cache, or rely on PHPUnit layout smoke',
  },
  {
    key: 'booking-confirmation',
    path: '/booking/confirmation',
    label: 'Booking confirmation',
    highRisk: true,
    skipReason:
      'Requires completed checkout session — same fixture gate as booking review',
  },
  { key: 'support', path: '/support', label: 'Support' },
  { key: 'about-us', path: '/about-us', label: 'About us' },
  { key: 'flights-search', path: '/flights/search', label: 'Flight search', highRisk: true, interactive: true },
  {
    key: 'flights-results',
    path: `/flights/results?${flightResultsQuery}`,
    label: 'Flight results',
    highRisk: true,
  },
  { key: 'register-agent', path: '/agent/register/apply', label: 'Agent registration apply' },
];

export const CUSTOMER_PAGES: AuditPageDef[] = [
  { key: 'dashboard', path: '/customer', label: 'Customer dashboard', requiresAuth: true },
  { key: 'bookings', path: '/customer/bookings', label: 'Customer bookings', highRisk: true, requiresAuth: true },
  {
    key: 'booking-detail',
    path: '/customer/bookings/{id}',
    label: 'Customer booking detail',
    requiresAuth: true,
    skipReason: 'Dynamic booking id — resolved at runtime if list has rows',
  },
  { key: 'travelers', path: '/customer/travelers', label: 'Customer travelers', requiresAuth: true },
  { key: 'support-hub', path: '/customer/support', label: 'Customer support hub', requiresAuth: true },
  { key: 'support-tickets', path: '/customer/support/tickets', label: 'Customer support tickets', requiresAuth: true },
  { key: 'profile', path: '/profile', label: 'Profile settings', requiresAuth: true, interactive: true },
];

export const AGENT_PAGES: AuditPageDef[] = [
  { key: 'dashboard', path: '/agent', label: 'Agent dashboard', requiresAuth: true, interactive: true },
  { key: 'bookings', path: '/agent/bookings', label: 'Agent bookings', highRisk: true, requiresAuth: true },
  { key: 'bookings-create', path: '/agent/bookings/create', label: 'Agent booking create', requiresAuth: true },
  {
    key: 'booking-detail',
    path: '/agent/bookings/{id}',
    label: 'Agent booking detail',
    requiresAuth: true,
    skipReason: 'Dynamic booking id — resolved at runtime if list has rows',
  },
  { key: 'wallet', path: '/agent/wallet', label: 'Agent wallet', highRisk: true, requiresAuth: true, interactive: true },
  { key: 'ledger', path: '/agent/ledger', label: 'Agent ledger', highRisk: true, requiresAuth: true },
  { key: 'deposits', path: '/agent/deposits', label: 'Agent deposits', highRisk: true, requiresAuth: true },
  { key: 'deposits-create', path: '/agent/deposits/create', label: 'Agent deposit create', requiresAuth: true },
  { key: 'agency', path: '/agent/agency', label: 'Agent agency details', requiresAuth: true },
  { key: 'agency-edit', path: '/agent/agency/edit', label: 'Agent agency edit', requiresAuth: true },
  { key: 'staff', path: '/agent/staff', label: 'Agent staff management', highRisk: true, requiresAuth: true },
  { key: 'support-tickets', path: '/agent/support/tickets', label: 'Agent support tickets', requiresAuth: true },
  {
    key: 'support-tickets-create',
    path: '/agent/support/tickets/create',
    label: 'Agent support ticket create',
    requiresAuth: true,
  },
  { key: 'travelers', path: '/agent/travelers', label: 'Agent travelers', requiresAuth: true },
  { key: 'profile', path: '/profile', label: 'Profile settings', requiresAuth: true, interactive: true },
];

export const AGENT_STAFF_RESTRICTED_PAGES: AuditPageDef[] = [
  { key: 'dashboard', path: '/agent', label: 'Agent staff dashboard', requiresAuth: true, interactive: true },
  { key: 'profile', path: '/profile', label: 'Profile settings', requiresAuth: true, interactive: true },
  { key: 'bookings', path: '/agent/bookings', label: 'Bookings (expect 403)', requiresAuth: true },
  { key: 'wallet', path: '/agent/wallet', label: 'Wallet (expect 403)', requiresAuth: true },
  { key: 'ledger', path: '/agent/ledger', label: 'Ledger (expect 403)', requiresAuth: true },
  { key: 'agency', path: '/agent/agency', label: 'Agency (expect 403)', requiresAuth: true },
  {
    key: 'agency-edit',
    path: '/agent/agency/edit',
    label: 'Agency edit (expect 403)',
    requiresAuth: true,
  },
];

export const AGENT_STAFF_FULL_PAGES: AuditPageDef[] = [
  { key: 'dashboard', path: '/agent', label: 'Agent staff dashboard', requiresAuth: true, interactive: true },
  { key: 'bookings', path: '/agent/bookings', label: 'Agent staff bookings', highRisk: true, requiresAuth: true },
  { key: 'bookings-create', path: '/agent/bookings/create', label: 'Agent staff booking create', requiresAuth: true },
  { key: 'wallet', path: '/agent/wallet', label: 'Agent staff wallet', highRisk: true, requiresAuth: true, interactive: true },
  { key: 'ledger', path: '/agent/ledger', label: 'Agent staff ledger', highRisk: true, requiresAuth: true },
  { key: 'deposits', path: '/agent/deposits', label: 'Agent staff deposits', requiresAuth: true },
  { key: 'agency', path: '/agent/agency', label: 'Agent staff agency (view-only)', requiresAuth: true, interactive: true },
  {
    key: 'agency-edit',
    path: '/agent/agency/edit',
    label: 'Agency edit (expect 403 for staff)',
    requiresAuth: true,
  },
  { key: 'support-tickets', path: '/agent/support/tickets', label: 'Agent staff support', requiresAuth: true },
  { key: 'travelers', path: '/agent/travelers', label: 'Agent staff travelers', requiresAuth: true },
  { key: 'profile', path: '/profile', label: 'Profile settings', requiresAuth: true, interactive: true },
];

export const ADMIN_PAGES: AuditPageDef[] = [
  { key: 'dashboard', path: '/admin', label: 'Admin dashboard', requiresAuth: true },
  { key: 'bookings', path: '/admin/bookings', label: 'Admin bookings', highRisk: true, requiresAuth: true },
  { key: 'agents', path: '/admin/agents', label: 'Admin agents', requiresAuth: true },
  { key: 'staff', path: '/admin/staff', label: 'Admin staff', requiresAuth: true },
  { key: 'users', path: '/admin/users', label: 'Admin users', requiresAuth: true },
  { key: 'markups', path: '/admin/markups', label: 'Admin markups', requiresAuth: true },
  { key: 'commissions', path: '/admin/commissions', label: 'Admin commissions', requiresAuth: true },
  {
    key: 'commission-detail',
    path: '/admin/commissions/{agent}',
    label: 'Admin commission detail',
    requiresAuth: true,
    skipReason: 'Dynamic agent id — resolved at runtime if commissions list has rows',
  },
  { key: 'reports', path: '/admin/reports', label: 'Admin reports', highRisk: true, requiresAuth: true },
  { key: 'api-settings', path: '/admin/api-settings', label: 'Admin API settings', requiresAuth: true },
  { key: 'settings', path: '/admin/settings', label: 'Admin settings hub', highRisk: true, requiresAuth: true },
  {
    key: 'settings-payments',
    path: '/admin/settings/payments',
    label: 'Admin payment settings',
    requiresAuth: true,
  },
  { key: 'support-tickets', path: '/admin/support/tickets', label: 'Admin support tickets', requiresAuth: true },
  { key: 'agent-deposits', path: '/admin/agent-deposits', label: 'Admin agent deposits', requiresAuth: true },
  { key: 'profile', path: '/profile', label: 'Profile settings', requiresAuth: true, interactive: true },
];

export const STAFF_PAGES: AuditPageDef[] = [
  { key: 'dashboard', path: '/staff', label: 'Staff dashboard', requiresAuth: true },
  { key: 'bookings', path: '/staff/bookings', label: 'Staff bookings', highRisk: true, requiresAuth: true },
  { key: 'support-tickets', path: '/staff/support/tickets', label: 'Staff support tickets', requiresAuth: true },
  { key: 'profile', path: '/profile', label: 'Profile settings', requiresAuth: true, interactive: true },
];

export const ROLE_MANIFESTS: RoleManifest[] = [
  { role: 'guest', label: 'Guest', screenshotDir: 'public', pages: PUBLIC_PAGES },
  {
    role: 'customer',
    label: 'Customer',
    storageState: 'UI_test/.auth/customer.json',
    screenshotDir: 'customer',
    pages: CUSTOMER_PAGES,
  },
  {
    role: 'agent',
    label: 'Agent Admin',
    storageState: 'UI_test/.auth/agent.json',
    screenshotDir: 'agent',
    pages: AGENT_PAGES,
  },
  {
    role: 'agent_staff_restricted',
    label: 'Agent Staff (restricted)',
    storageState: 'UI_test/.auth/agent_staff_restricted.json',
    screenshotDir: 'agent_staff_restricted',
    pages: AGENT_STAFF_RESTRICTED_PAGES,
  },
  {
    role: 'agent_staff_full',
    label: 'Agent Staff (broad)',
    storageState: 'UI_test/.auth/agent_staff_full.json',
    screenshotDir: 'agent_staff_full',
    pages: AGENT_STAFF_FULL_PAGES,
  },
  {
    role: 'admin',
    label: 'Admin',
    storageState: 'UI_test/.auth/admin.json',
    screenshotDir: 'admin',
    pages: ADMIN_PAGES,
  },
  {
    role: 'staff',
    label: 'Platform Staff',
    storageState: 'UI_test/.auth/staff.json',
    screenshotDir: 'staff',
    pages: STAFF_PAGES,
  },
];

export function skippedPagesFromManifests(manifests: RoleManifest[], authReady: Set<AuditRole>): SkippedPage[] {
  const skipped: SkippedPage[] = [];

  for (const manifest of manifests) {
    if (manifest.role !== 'guest' && !authReady.has(manifest.role)) {
      for (const page of manifest.pages) {
        skipped.push({
          role: manifest.role,
          pageKey: page.key,
          path: page.path,
          reason: `Auth not available for role ${manifest.role}`,
        });
      }
      continue;
    }

    for (const page of manifest.pages) {
      if (page.skipReason && (page.path.includes('{id}') || page.path.includes('{agent}'))) {
        skipped.push({
          role: manifest.role,
          pageKey: page.key,
          path: page.path,
          reason: page.skipReason,
        });
      } else if (
        page.skipReason &&
        (page.key === 'booking-review' || page.key === 'booking-confirmation')
      ) {
        skipped.push({
          role: manifest.role,
          pageKey: page.key,
          path: page.path,
          reason: page.skipReason,
        });
      }
    }
  }

  return skipped;
}

export function roleDirFor(role: AuditRole): string {
  return ROLE_MANIFESTS.find((m) => m.role === role)?.screenshotDir ?? role;
}

/** Roles included in this run (OTA_AUDIT_ROLES comma list, or all roles). */
export function activeRoleManifests(): RoleManifest[] {
  const filter = process.env.OTA_AUDIT_ROLES?.split(',').map((s) => s.trim()).filter(Boolean);
  if (!filter?.length) {
    return ROLE_MANIFESTS;
  }

  return ROLE_MANIFESTS.filter((m) => filter.includes(m.role));
}
