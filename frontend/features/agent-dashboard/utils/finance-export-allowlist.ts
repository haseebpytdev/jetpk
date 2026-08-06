/**
 * Finance statement CSV export must remain a same-origin Laravel handoff only.
 */
export function resolveAllowedFinanceExportUrl(url: string | null | undefined): string | null {
  if (!url) return null;
  if (!url.startsWith("/laravel/agent/finance/statement/export")) {
    return null;
  }
  return url;
}
