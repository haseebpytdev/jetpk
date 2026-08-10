const PRIVATE_ORIGIN =
  /^(https?:\/\/)?(127\.0\.0\.1|localhost)(:\d+)?/i;

/**
 * Ensures browser-visible links use relative public paths, never loopback origins.
 */
export function sanitizePublicHref(href: string): string {
  if (!href) {
    return href;
  }

  if (href.startsWith("/") && !href.startsWith("//")) {
    return href;
  }

  try {
    const url = new URL(href, "https://jetpakistan.pk");
    const isPrivate =
      PRIVATE_ORIGIN.test(url.origin) ||
      url.hostname === "127.0.0.1" ||
      url.hostname === "localhost" ||
      url.port === "8088";

    if (isPrivate) {
      return `${url.pathname}${url.search}${url.hash}`;
    }

    return href;
  } catch {
    return href;
  }
}

export function containsPrivateBrowserTarget(value: string): boolean {
  const lower = value.toLowerCase();

  return (
    lower.includes("127.0.0.1") ||
    lower.includes("localhost") ||
    lower.includes(":8088")
  );
}
