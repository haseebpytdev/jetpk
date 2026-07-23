export type NavItem = {
  label: string;
  href: string;
  laravelRoute?: string;
  planned?: boolean;
  children?: NavItem[];
};

export const primaryNav: NavItem[] = [
  { label: "Dashboard", href: "/", laravelRoute: "admin.dashboard" },
  {
    label: "Bookings",
    href: "/planned/bookings",
    laravelRoute: "admin.bookings",
    planned: true,
  },
  {
    label: "Payments",
    href: "/planned/bookings?queue=payment_review",
    laravelRoute: "admin.bookings",
    planned: true,
  },
  {
    label: "Tickets",
    href: "/planned/bookings?queue=ticketing",
    laravelRoute: "admin.bookings",
    planned: true,
  },
  {
    label: "Cancellations",
    href: "/planned/bookings?queue=cancellations",
    laravelRoute: "admin.bookings",
    planned: true,
  },
  {
    label: "Customers",
    href: "/planned/customers",
    laravelRoute: "admin.customers.index",
    planned: true,
  },
  {
    label: "Agents",
    href: "/planned/agents",
    laravelRoute: "admin.agents",
    planned: true,
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
  {
    label: "Flights & Search",
    href: "/planned/flights",
    laravelRoute: "flights.search",
    planned: true,
  },
  {
    label: "Suppliers",
    href: "/planned/suppliers",
    laravelRoute: "admin.api-settings",
    planned: true,
  },
  {
    label: "Markups & Settings",
    href: "/planned/markups",
    laravelRoute: "admin.markups",
    planned: true,
  },
  {
    label: "CMS & Pages",
    href: "/planned/page-settings",
    laravelRoute: "admin.page-settings.index",
    planned: true,
  },
  {
    label: "Reports",
    href: "/planned/reports",
    laravelRoute: "admin.reports",
    planned: true,
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
];
