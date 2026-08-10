/** Shared Laravel origin helpers — safe for client and server bundles (no next/headers). */

export function buildCookieHeader(cookieList: Array<{ name: string; value: string }>): string {
  return cookieList.map((cookie) => `${cookie.name}=${cookie.value}`).join("; ");
}

export function getLaravelServerBase(): string {
  return (
    process.env.LARAVEL_URL ??
    process.env.NEXT_PUBLIC_LARAVEL_URL ??
    "http://127.0.0.1:8088"
  ).replace(/\/$/, "");
}
