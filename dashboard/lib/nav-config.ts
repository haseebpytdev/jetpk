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
        label: "Cancellations",
        href: "/planned/bookings?queue=cancellations",
        laravelRoute: "admin.bookings",
        planned: true,
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
      {
        label: "Staff Management",
        href: "/planned/users",
        laravelRoute: "admin.staff",
        planned: true,
      },
      {
        label: "Roles & Permissions",
        href: "/planned/users",
        laravelRoute: "admin.roles-permissions",
        planned: true,
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
        laravelRoute: "admin.api-settings",
      },
      {
        label: "Markups & Settings",
        href: "/planned/markups",
        laravelRoute: "admin.markups",
        planned: true,
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
        label: "Audit Logs",
        href: "/planned/diagnostics",
        laravelRoute: "admin.finance.wallet-audit.index",
        planned: true,
      },
      {
        label: "System Settings",
        href: "/planned/settings",
        laravelRoute: "admin.settings.index",
        planned: true,
      },
      {
        label: "Support & Help",
        href: "/planned/support",
        laravelRoute: "admin.support.tickets.index",
        planned: true,
      },
    ],
  },
];

/** Flat list for backwards compatibility */
export const primaryNav: NavItem[] = navGroups.flatMap((g) => g.items);
