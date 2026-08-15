/** Shared Laravel origin helpers — safe for client and server bundles (no next/headers). */

export function buildCookieHeader(cookieList: Array<{ name: string; value: string }>): string {
  return cookieList.map((cookie) => `${cookie.name}=${cookie.value}`).join("; ");
}

export function getLaravelServerBase(): string {
  const fromEnv = process.env.LARAVEL_URL?.replace(/\/$/, "");
  if (fromEnv) {
    return fromEnv;
  }

  // Server-only runtime target; never use NEXT_PUBLIC_* loopback fallbacks in client bundles.
  return "http://127.0.0.1:8088";
}
