/** Escape plain text for safe rendering in React text nodes. */
export function escapePlainText(value: string): string {
  return value
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");
}

/**
 * Very narrow HTML allowlist for CMS body_html from Laravel-sanitized content only.
 * Prefer structured content; use only when Laravel returns sanitized HTML.
 */
export function isTrustedCmsHtml(html: string): boolean {
  return !/<script|javascript:|on\w+=/i.test(html);
}
