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

export type DashboardPreviewPortal = "admin" | "staff";

/** Preview-mode fallback IA — mirrors Laravel BackOfficeCapabilitiesPresenter (JP-ADMIN-CMS-03). */
export const navGroups: NavGroup[] = [
  {
    label: "Overview",
    items: [{ label: "Dashboard", href: "/", laravelRoute: "admin.dashboard" }],
  },
  {
    label: "Operations",
    items: [
      { label: "Bookings", href: "/bookings", laravelRoute: "admin.bookings" },
      { label: "Execution", href: "/operations/execution", laravelRoute: "admin.bookings" },
      { label: "Cancellations", href: "/operations/review", laravelRoute: "admin.bookings" },
      { label: "PNRs", href: "/pnrs", laravelRoute: "admin.bookings" },
      { label: "Tickets", href: "/tickets", laravelRoute: "admin.bookings" },
    ],
  },
  {
    label: "Customers",
    items: [
      { label: "Customers", href: "/customers", laravelRoute: "admin.customers.index" },
      { label: "Agents", href: "/agents", laravelRoute: "admin.agents" },
      { label: "Agent applications", href: "/agents/applications", laravelRoute: "admin.agents" },
    ],
  },
  {
    label: "Finance",
    items: [
      { label: "Payments", href: "/payments", laravelRoute: "admin.payments" },
      { label: "Deposits", href: "/deposits", laravelRoute: "admin.agent-deposits.index" },
      { label: "Markups", href: "/markups", laravelRoute: "admin.markups" },
      { label: "Commissions", href: "/commissions", laravelRoute: "admin.commissions.index" },
      { label: "Accounting", href: "/accounting", laravelRoute: "admin.finance.adjustments.index" },
    ],
  },
  {
    label: "Suppliers",
    items: [
      { label: "Suppliers", href: "/suppliers", laravelRoute: "admin.suppliers" },
      { label: "API & Modules", href: "/integrations", laravelRoute: "admin.integrations.index" },
    ],
  },
  {
    label: "Website",
    items: [
      { label: "Homepage", href: "/cms/sections", laravelRoute: "admin.page-settings.index" },
      { label: "Pages", href: "/cms/pages", laravelRoute: "admin.page-settings.index" },
      { label: "Media library", href: "/cms/assets", laravelRoute: "admin.page-settings.index" },
    ],
  },
  {
    label: "Communications",
    items: [{ label: "Support", href: "/support", laravelRoute: "admin.support.tickets.index" }],
  },
  {
    label: "Reporting",
    items: [
      { label: "Reports", href: "/reports", laravelRoute: "admin.reports" },
      { label: "Audit", href: "/audit", laravelRoute: "admin.finance.wallet-audit.index" },
    ],
  },
  {
    label: "Administration",
    items: [
      { label: "Users", href: "/users", laravelRoute: "admin.users.index" },
      { label: "Staff", href: "/staff", laravelRoute: "admin.staff" },
      { label: "Roles & permissions", href: "/users/roles", laravelRoute: "admin.users.index" },
    ],
  },
  {
    label: "System",
    items: [
      { label: "Settings", href: "/settings", laravelRoute: "admin.settings.index" },
      { label: "Booking & Checkout", href: "/settings/booking-checkout", laravelRoute: "admin.settings.booking-checkout.show" },
      { label: "Promo codes", href: "/settings/promo-codes", laravelRoute: "admin.promo-codes.index" },
      { label: "System health", href: "/system/health" },
      { label: "Go-live checklist", href: "/system/go-live" },
    ],
  },
];

/** Preview-mode staff IA — excludes admin-only destinations. */
export const staffNavGroups: NavGroup[] = [
  {
    label: "Overview",
    items: [{ label: "Dashboard", href: "/", laravelRoute: "staff.dashboard" }],
  },
  {
    label: "Operations",
    items: [
      { label: "Bookings", href: "/bookings", laravelRoute: "staff.bookings" },
      { label: "Execution", href: "/operations/execution", laravelRoute: "staff.bookings" },
      { label: "Cancellations", href: "/operations/review", laravelRoute: "staff.bookings" },
      { label: "PNRs", href: "/pnrs", laravelRoute: "staff.bookings" },
      { label: "Tickets", href: "/tickets", laravelRoute: "staff.bookings" },
    ],
  },
  {
    label: "Customers",
    items: [
      { label: "Customers", href: "/customers", laravelRoute: "staff.customers.index" },
      { label: "Agents", href: "/agents", laravelRoute: "staff.agents" },
    ],
  },
  {
    label: "Finance",
    items: [{ label: "Payments", href: "/payments", laravelRoute: "staff.payments" }],
  },
  {
    label: "Suppliers",
    items: [{ label: "Suppliers", href: "/suppliers", laravelRoute: "staff.suppliers" }],
  },
  {
    label: "Website",
    items: [{ label: "Homepage", href: "/cms/sections", laravelRoute: "staff.page-settings.index" }],
  },
  {
    label: "Communications",
    items: [{ label: "Support", href: "/support", laravelRoute: "staff.support.tickets.index" }],
  },
  {
    label: "Reporting",
    items: [
      { label: "Reports", href: "/reports", laravelRoute: "staff.reports" },
      { label: "Audit", href: "/audit", laravelRoute: "staff.finance.wallet-audit.index" },
    ],
  },
  {
    label: "Administration",
    items: [{ label: "Users", href: "/users", laravelRoute: "staff.staff" }],
  },
  {
    label: "System",
    items: [{ label: "Settings", href: "/settings", laravelRoute: "staff.settings.index" }],
  },
];

export function previewNavGroupsForPortal(portal: DashboardPreviewPortal): NavGroup[] {
  return portal === "staff" ? staffNavGroups : navGroups;
}

export const primaryNav: NavItem[] = navGroups.flatMap((g) => g.items);
