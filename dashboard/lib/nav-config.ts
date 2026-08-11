export type NavItem = {
  label: string;
  href: string;
  laravelRoute?: string;
  children?: NavItem[];
};

export type NavGroup = {
  label: string;
  items: NavItem[];
};

/** Preview-mode fallback IA — mirrors Laravel BackOfficeCapabilitiesPresenter groups. */
export const navGroups: NavGroup[] = [
  {
    label: "Overview",
    items: [{ label: "Dashboard", href: "/", laravelRoute: "admin.dashboard" }],
  },
  {
    label: "Booking operations",
    items: [
      { label: "Bookings", href: "/bookings", laravelRoute: "admin.bookings" },
      { label: "Execution", href: "/operations/execution", laravelRoute: "admin.bookings" },
      { label: "Cancellations", href: "/operations/review", laravelRoute: "admin.bookings" },
      { label: "PNRs", href: "/pnrs", laravelRoute: "admin.bookings" },
      { label: "Tickets", href: "/tickets", laravelRoute: "admin.bookings" },
    ],
  },
  {
    label: "Finance",
    items: [
      { label: "Payments", href: "/payments", laravelRoute: "admin.payments" },
      { label: "Deposits", href: "/deposits", laravelRoute: "admin.agent-deposits.index" },
      { label: "Markups", href: "/settings", laravelRoute: "admin.markups" },
      { label: "Commissions", href: "/reports", laravelRoute: "admin.commissions.index" },
    ],
  },
  {
    label: "Customers & distribution",
    items: [
      { label: "Customers", href: "/customers", laravelRoute: "admin.customers.index" },
      { label: "Agents", href: "/agents", laravelRoute: "admin.agents" },
    ],
  },
  {
    label: "Suppliers",
    items: [
      { label: "Suppliers", href: "/suppliers", laravelRoute: "admin.api-settings" },
    ],
  },
  {
    label: "Content & website",
    items: [
      { label: "CMS", href: "/cms", laravelRoute: "admin.page-settings.index" },
    ],
  },
  {
    label: "Communications",
    items: [
      { label: "Support", href: "/support", laravelRoute: "admin.support.tickets.index" },
    ],
  },
  {
    label: "Administration",
    items: [
      {
        label: "Users",
        href: "/users",
        laravelRoute: "admin.staff",
        children: [
          { label: "Directory", href: "/users" },
          { label: "Roles", href: "/users/roles" },
          { label: "Permissions", href: "/users/permissions" },
        ],
      },
    ],
  },
  {
    label: "Reporting",
    items: [
      { label: "Reports", href: "/reports", laravelRoute: "admin.reports" },
      { label: "Audit", href: "/audit", laravelRoute: "admin.finance.wallet-audit.index" },
    ],
  },
  {
    label: "System",
    items: [
      {
        label: "Settings",
        href: "/settings",
        laravelRoute: "admin.settings.index",
        children: [
          { label: "Overview", href: "/settings" },
          { label: "General", href: "/settings/general" },
          { label: "Security", href: "/settings/security" },
          { label: "Notifications", href: "/settings/notifications" },
          { label: "Integrations", href: "/settings/integrations" },
        ],
      },
    ],
  },
];

export const primaryNav: NavItem[] = navGroups.flatMap((g) => g.items);
