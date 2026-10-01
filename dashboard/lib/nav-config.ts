export type NavItem = {
  label: string;
  href: string;
  laravelRoute?: string;
  planned?: boolean;
  children?: NavItem[];
};

export type NavGroup = {
  label: string;
  items: NavItem[];
};

export const navGroups: NavGroup[] = [
  {
    label: "Operations",
    items: [
      { label: "Dashboard", href: "/", laravelRoute: "admin.dashboard" },
      { label: "Bookings", href: "/bookings", laravelRoute: "admin.bookings" },
      {
        label: "Payments",
        href: "/payments",
        laravelRoute: "admin.payments",
      },
      {
        label: "PNRs",
        href: "/pnrs",
        laravelRoute: "admin.bookings",
      },
      {
        label: "Tickets",
        href: "/tickets",
        laravelRoute: "admin.bookings",
      },
      {
        label: "Live operations",
        href: "/operations/inbox",
        laravelRoute: "admin.operations.inbox",
      },
      {
        label: "Cancellations",
        href: "/operations/review",
        laravelRoute: "admin.bookings.cancellations.index",
      },
      {
        label: "Execution",
        href: "/operations/execution",
        laravelRoute: "admin.bookings",
      },
      {
        label: "Notification failures",
        href: "/notifications/failures",
        laravelRoute: "admin.settings.communications.delivery-log.index",
      },
    ],
  },
  {
    label: "Customers & partners",
    items: [
      {
        label: "Customers",
        href: "/customers",
        laravelRoute: "admin.customers.index",
      },
      {
        label: "Agents",
        href: "/agents",
        laravelRoute: "admin.agents",
      },
    ],
  },
  {
    label: "Access control",
    items: [
      {
        label: "Users",
        href: "/users",
        laravelRoute: "admin.users.index",
        children: [
          { label: "Users", href: "/users" },
          { label: "Roles", href: "/users/roles" },
          { label: "Permissions", href: "/users/permissions" },
        ],
      },
      {
        label: "Staff",
        href: "/staff",
        laravelRoute: "admin.staff",
      },
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
      {
        label: "Audit",
        href: "/audit",
        laravelRoute: "admin.finance.wallet-audit.index",
      },
    ],
  },
  {
    label: "Inventory & pricing",
    items: [
      {
        label: "Flights & Search",
        href: "/planned/flights",
        laravelRoute: "flights.search",
        planned: true,
      },
      {
        label: "Suppliers",
        href: "/suppliers",
      },
      {
        label: "API Connections",
        href: "/api-connections",
      },
      {
        label: "Markups & Settings",
        href: "/markups",
        laravelRoute: "admin.markups",
      },
      {
        label: "Commissions",
        href: "/commissions",
        laravelRoute: "admin.commissions.index",
      },
      {
        label: "CMS",
        href: "/cms",
        laravelRoute: "admin.page-settings.index",
        children: [
          { label: "Overview", href: "/cms" },
          { label: "Pages", href: "/cms/pages" },
          { label: "Sections", href: "/cms/sections" },
          { label: "Banners", href: "/cms/banners" },
          { label: "Notices", href: "/cms/notices" },
          { label: "Assets", href: "/cms/assets" },
        ],
      },
    ],
  },
  {
    label: "Insights & system",
    items: [
      {
        label: "Reports",
        href: "/reports",
        laravelRoute: "admin.reports",
        children: [
          { label: "Overview", href: "/reports" },
          { label: "Sales", href: "/reports/sales" },
          { label: "Bookings", href: "/reports/bookings" },
          { label: "Payments", href: "/reports/payments" },
          { label: "Operations", href: "/reports/operations" },
        ],
      },
      {
        label: "Notifications",
        href: "/planned/communications",
        laravelRoute: "admin.settings.communications.index",
        planned: true,
      },
      {
        label: "Support & Help",
        href: "/support",
        laravelRoute: "admin.support.tickets.index",
      },
    ],
  },
];

/** Flat list for backwards compatibility */
export const primaryNav: NavItem[] = navGroups.flatMap((g) => g.items);
