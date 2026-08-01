/**
 * Mirrors Laravel `ReservedPublicPath::FIRST_SEGMENT` for Next.js custom CMS slug routes.
 */
export const RESERVED_PUBLIC_FIRST_SEGMENTS = new Set([
  "admin",
  "agent",
  "staff",
  "customer",
  "account",
  "dashboard",
  "booking",
  "bookings",
  "flights",
  "login",
  "logout",
  "register",
  "password",
  "email",
  "verification",
  "support",
  "contact",
  "api",
  "storage",
  "health",
  "up",
  "dev",
  "devcp",
  "oauth",
  "auth",
  "payment",
  "payments",
  "webhook",
  "webhooks",
  "callbacks",
  "sitemap.xml",
  "robots.txt",
  "lookup-booking",
  "guest",
  "profile",
  "airports",
  "groups",
  "pages",
  "about-us",
  "about",
  "faq",
  "terms",
  "privacy",
  "checkout",
  "assets",
  "build",
  "vendor",
  "client-assets",
  "themes",
  "ui",
  "v1",
  "v2",
  "umrah-groups",
  "request-demo",
  "agent-network",
  "mobile-view",
  "mobile-app-preview",
  "desktop-view",
  "forgot-password",
  "reset-password",
  "verify-email",
  "preview",
  "dev-cp",
  "legal",
  "sitemap",
  "offers",
  "hotels",
  "travel-services",
  "careers",
  "press",
  "investors",
  "manage-booking",
  "access-denied",
  "laravel",
  "_next",
]);

export function normalizePublicSlug(slug: string): string {
  return slug
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9.\-]+/g, "-")
    .replace(/^-+|-+$/g, "");
}

export function isReservedPublicSlug(slug: string): boolean {
  const normalized = normalizePublicSlug(slug);
  return normalized === "" || RESERVED_PUBLIC_FIRST_SEGMENTS.has(normalized);
}
