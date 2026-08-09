const ADMIN_ROUTE_PATHS: Record<string, string> = {
  "admin.dashboard": "/admin/dashboard",
  "admin.bookings": "/admin/bookings",
  "admin.payments": "/admin/payments",
  "admin.customers.index": "/admin/customers",
  "admin.agents": "/admin/agents",
  "admin.staff": "/admin/staff",
  "admin.api-settings": "/admin/api-settings",
  "admin.markups": "/admin/markups",
  "admin.page-settings.index": "/admin/page-settings",
  "admin.reports": "/admin/reports",
  "admin.settings.index": "/admin/settings",
  "admin.settings.communications.index": "/admin/settings/communications",
  "admin.support.tickets.index": "/admin/support/tickets",
  "admin.finance.wallet-audit.index": "/admin/finance/wallet-audit",
  "admin.agent-deposits.index": "/admin/agent-deposits",
  "flights.search": "/flights/search",
  "staff.dashboard": "/staff/dashboard",
  "staff.bookings": "/staff/bookings",
};

export function laravelRouteUrl(
  routeName: string,
  params?: Record<string, string | undefined | null>,
): string {
  const base = ADMIN_ROUTE_PATHS[routeName] ?? `/${routeName.replace(/\./g, "/")}`;
  if (!params) {
    return base;
  }

  const search = new URLSearchParams();
  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined && value !== null && value !== "") {
      search.set(key, value);
    }
  }

  const query = search.toString();
  return query ? `${base}?${query}` : base;
}
