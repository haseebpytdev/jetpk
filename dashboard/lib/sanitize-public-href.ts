/**
 * Allow only same-origin absolute public paths for Laravel deep links.
 * Rejects protocol-relative, external, and javascript: URLs.
 */
export function sanitizePublicHref(href: string): string {
  const value = (href ?? "").trim();
  if (value === "") {
    return "/";
  }
  if (value.startsWith("/") && !value.startsWith("//")) {
    return value;
  }
  return "/";
}
