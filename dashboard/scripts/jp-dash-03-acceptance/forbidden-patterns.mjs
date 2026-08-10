/** Forbidden browser-visible patterns for JP-DASH-03 production acceptance. */

export const PRIVATE_ORIGIN_PATTERNS = [
  /127\.0\.0\.1/i,
  /localhost/i,
  /:8088/,
];

export const PREVIEW_RESIDUE_PATTERNS = [
  /Preview data/i,
  /synthetic records/i,
  /Not live production data/i,
  /Local preview editing/i,
  /Active preview values/i,
  /Apply to preview/i,
  /Reset preview/i,
  /\bPLANNED\b/,
  /Coming soon/i,
  /Dashboard unavailable/i,
  /Dashboard temporarily unavailable/i,
  /Preview only/i,
  /Mock feed/i,
  /synthetic preview data/i,
];

export const NEXT_ADMIN_PAGES = [
  "/admin/dashboard",
  "/admin/dashboard/bookings",
  "/admin/dashboard/payments",
  "/admin/dashboard/pnrs",
  "/admin/dashboard/tickets",
  "/admin/dashboard/deposits",
  "/admin/dashboard/customers",
  "/admin/dashboard/agents",
  "/admin/dashboard/suppliers",
  "/admin/dashboard/users",
  "/admin/dashboard/cms",
  "/admin/dashboard/reports",
  "/admin/dashboard/audit",
  "/admin/dashboard/settings",
  "/admin/dashboard/support",
];

export const LARAVEL_ADMIN_HANDOFFS = [
  { label: "Staff", href: "/admin/staff" },
  { label: "API Settings", href: "/admin/api-settings" },
  { label: "Page Settings", href: "/admin/page-settings" },
  { label: "Branding", href: "/admin/settings/branding" },
  { label: "Markups", href: "/admin/markups" },
  { label: "Support", href: "/admin/support/tickets" },
  { label: "Cancellations", href: "/admin/bookings?queue=cancellations" },
  { label: "Execution queue", href: "/admin/bookings?queue=needs_action" },
  { label: "Flight search", href: "/" },
  { label: "Go-live checklist", href: "/admin/go-live-checklist" },
  { label: "Laravel Settings", href: "/admin/settings" },
  { label: "Communications", href: "/admin/settings/communications" },
  { label: "Agent deposits", href: "/admin/agent-deposits" },
];

export function scanTextForHits(text, patterns) {
  const hits = [];
  for (const pattern of patterns) {
    if (pattern.test(text)) {
      hits.push(pattern.source);
    }
  }
  return hits;
}
