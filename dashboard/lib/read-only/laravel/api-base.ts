/**
 * Laravel API base URL for dashboard read-only calls.
 *
 * Browser: prefer explicit NEXT_PUBLIC_LARAVEL_API_BASE, else same-origin `/laravel`
 * (OLS bridges `/laravel` → public Next → Laravel). Never use bare `/api/...` from
 * the dashboard origin — that 404s on Next :3001.
 *
 * Server: prefer LARAVEL_URL / NEXT_PUBLIC_LARAVEL_URL, else public `/laravel` mount.
 */
export function getLaravelApiBase(): string {
  const configured = (process.env.NEXT_PUBLIC_LARAVEL_API_BASE ?? "").replace(/\/$/, "");
  if (configured !== "") {
    return configured;
  }

  if (typeof window === "undefined") {
    const laravel =
      process.env.LARAVEL_URL?.trim().replace(/\/$/, "") ||
      process.env.NEXT_PUBLIC_LARAVEL_URL?.trim().replace(/\/$/, "") ||
      "";
    if (laravel !== "") {
      return laravel;
    }
    const appUrl =
      process.env.NEXT_PUBLIC_APP_URL?.trim().replace(/\/$/, "") ||
      process.env.APP_URL?.trim().replace(/\/$/, "") ||
      "https://jetpakistan.pk";
    return `${appUrl}/laravel`;
  }

  return "/laravel";
}

export function dashboardApiUrl(path: string): string {
  const normalized = path.startsWith("/") ? path : `/${path}`;
  return `${getLaravelApiBase()}/api/dashboard${normalized}`;
}

export const DASHBOARD_API_ROUTES = {
  session: "/session",
  overview: "/overview",
  bookings: "/bookings",
  bookingDetail: (id: string) => `/bookings/${encodeURIComponent(id)}`,
  payments: "/payments",
  paymentDetail: (id: string) => `/payments/${encodeURIComponent(id)}`,
  customers: "/customers",
  customerDetail: (id: string) => `/customers/${encodeURIComponent(id)}`,
  suppliers: "/suppliers",
  supplierDetail: (id: string) => `/suppliers/${encodeURIComponent(id)}`,
  agents: "/agents",
  agentDetail: (id: string) => `/agents/${encodeURIComponent(id)}`,
  pnrs: "/pnrs",
  pnrDetail: (id: string) => `/pnrs/${encodeURIComponent(id)}`,
  tickets: "/tickets",
  ticketDetail: (id: string) => `/tickets/${encodeURIComponent(id)}`,
  reportsSummary: "/reports/summary",
  reportsBookings: "/reports/bookings",
  reportsPayments: "/reports/payments",
  reportsSuppliers: "/reports/suppliers",
  reportsAgents: "/reports/agents",
  cmsPages: "/cms/pages",
  cmsPageDetail: (id: string) => `/cms/pages/${encodeURIComponent(id)}`,
  cmsPageSections: (id: string) => `/cms/pages/${encodeURIComponent(id)}/sections`,
  users: "/users",
  userDetail: (id: string) => `/users/${encodeURIComponent(id)}`,
  roles: "/roles",
  roleDetail: (id: string) => `/roles/${encodeURIComponent(id)}`,
  permissions: "/permissions",
  permissionDetail: (id: string) => `/permissions/${encodeURIComponent(id)}`,
  rbacMatrix: "/rbac/matrix",
  settings: "/settings",
  settingsGeneral: "/settings/general",
  settingsSecurity: "/settings/security",
  settingsNotifications: "/settings/notifications",
  settingsIntegrations: "/settings/integrations",
  audit: "/audit",
  auditDetail: (id: string) => `/audit/${encodeURIComponent(id)}`,
  deposits: "/deposits",
  depositDetail: (id: string) => `/deposits/${encodeURIComponent(id)}`,
} as const;
