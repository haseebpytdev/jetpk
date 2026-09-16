import type { CurrencyOption, FooterColumn, NavItem } from "@/types/navigation";

export type PublicModuleStatus =
  | "ENABLED_REAL_ROUTE"
  | "CMS_REAL_ROUTE"
  | "DISABLED"
  | "PLANNED"
  | "NONEXISTENT";

export type PublicNavigationModule = {
  label: string;
  status: PublicModuleStatus;
  href?: string;
  notes: string;
};

/** Authoritative enabled-module contract for the public header (JETPK-UI-003). */
export const publicNavigationAuthority: PublicNavigationModule[] = [
  {
    label: "Flights",
    status: "ENABLED_REAL_ROUTE",
    href: "/#flight-search",
    notes: "Dropdown: search flights and manage booking",
  },
  {
    label: "Groups",
    status: "ENABLED_REAL_ROUTE",
    href: "/groups",
    notes: "Groups landing — discovery + search handoff to /groups/search",
  },
  {
    label: "Support",
    status: "ENABLED_REAL_ROUTE",
    href: "/support",
    notes: "Dropdown: help center, contact, FAQ",
  },
  {
    label: "Hotels",
    status: "NONEXISTENT",
    notes: "No operational hotels module; intentionally hidden from navigation",
  },
  {
    label: "Offers",
    status: "NONEXISTENT",
    notes: "No standalone offers route; homepage promos are presentation-only",
  },
  {
    label: "Travel Services",
    status: "NONEXISTENT",
    notes: "No travel services hub route; intentionally hidden from navigation",
  },
];

export const intentionallyHiddenNavigationModules = publicNavigationAuthority
  .filter((module) => module.status === "NONEXISTENT" || module.status === "DISABLED" || module.status === "PLANNED")
  .map((module) => module.label);

export type FooterNewsletterDisposition = {
  supported: boolean;
  disposition: string;
};

function collectNavHrefs(items: NavItem[]): string[] {
  return items.flatMap((item) => {
    if (item.type === "link") return [item.href];
    return item.items.map((child) => child.href);
  });
}

export const primaryNavigation: NavItem[] = [
  {
    type: "dropdown",
    label: "Flights",
    items: [
      { label: "Search Flights", href: "/#flight-search", description: "Compare fares across airlines" },
      { label: "Manage Booking", href: "/lookup-booking", description: "Retrieve an existing booking" },
    ],
  },
  { type: "link", label: "Groups", href: "/groups", badge: "New" },
  {
    type: "dropdown",
    label: "Support",
    items: [
      { label: "Help Center", href: "/support", description: "Browse help articles" },
      { label: "Contact Us", href: "/contact", description: "Reach our support team" },
      { label: "FAQs", href: "/faq", description: "Common booking questions" },
    ],
  },
];

/**
 * Role-aware primary nav for signed-in users so Support leads to the account
 * support workspace instead of only the public help center.
 */
export function primaryNavigationForSession(session?: {
  status: string;
  accountType?: string | null;
  portalType?: string | null;
  dashboardUrl?: string;
} | null): NavItem[] {
  if (!session || session.status !== "authenticated") {
    return primaryNavigation;
  }

  const supportItems = [...(primaryNavigation.find((item) => item.label === "Support" && item.type === "dropdown") as Extract<NavItem, { type: "dropdown" }>).items];

  if (session.accountType === "customer") {
    supportItems.unshift({
      label: "My support requests",
      href: "/customer/support",
      description: "View and reply to your support tickets",
    });
  } else if (session.accountType === "agent") {
    supportItems.unshift({
      label: "Agency support",
      href: "/agent/support",
      description: "Open support cases for your agency",
    });
  } else if (
    session.accountType === "staff" ||
    session.portalType === "staff" ||
    session.accountType === "platform_admin" ||
    session.accountType === "admin" ||
    session.portalType === "admin"
  ) {
    supportItems.unshift({
      label: "Operations dashboard",
      href: session.dashboardUrl || (session.portalType === "admin" || session.accountType === "platform_admin" || session.accountType === "admin" ? "/admin/dashboard" : "/staff/dashboard"),
      description: "Continue to your work queue and assignments",
    });
  }

  return primaryNavigation.map((item) => {
    if (item.type === "dropdown" && item.label === "Support") {
      return { ...item, items: supportItems };
    }
    return item;
  });
}
export const footerColumns: FooterColumn[] = [
  {
    title: "Explore",
    links: [
      { label: "Flights", href: "/" },
      { label: "Groups", href: "/groups" },
      { label: "Manage Booking", href: "/lookup-booking" },
    ],
  },
  {
    title: "Company",
    links: [
      { label: "About Us", href: "/about-us" },
      { label: "Sitemap", href: "/sitemap" },
    ],
  },
  {
    title: "Support",
    links: [
      { label: "Help Center", href: "/support" },
      { label: "Contact Us", href: "/contact" },
      { label: "FAQ", href: "/faq" },
      { label: "Manage Booking", href: "/lookup-booking" },
    ],
  },
  {
    title: "Legal",
    links: [
      { label: "Terms & Conditions", href: "/terms" },
      { label: "Privacy Policy", href: "/privacy" },
    ],
  },
];

/** Authoritative footer information architecture (JETPK-UI-014). */
export const footerInformationArchitecture = {
  contentColumnCount: footerColumns.length,
  columns: footerColumns.map((column) => ({
    title: column.title,
    links: column.links.map((link) => ({ label: link.label, href: link.href })),
  })),
  newsletter: {
    supported: false,
    disposition: "No newsletter subscription endpoint; interactive subscribe UI is not rendered",
  } satisfies FooterNewsletterDisposition,
};

export const authoritativePrimaryNavigationHrefs = collectNavHrefs(primaryNavigation);

export const socialLinks = [
  { label: "Facebook", href: "https://www.facebook.com/jetpakistancom/" },
  { label: "Instagram", href: "https://www.instagram.com/jetpakistanofficial" },
];

export const currencyOptions: CurrencyOption[] = [
  { code: "PKR", label: "Pakistani Rupee", symbol: "Rs", flagLabel: "Pakistan" },
  { code: "USD", label: "US Dollar", symbol: "$", flagLabel: "United States" },
  { code: "AED", label: "UAE Dirham", symbol: "AED", flagLabel: "United Arab Emirates" },
  { code: "SAR", label: "Saudi Riyal", symbol: "SAR", flagLabel: "Saudi Arabia" },
];
