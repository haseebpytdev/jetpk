import type { PublicSession } from "@/types/session";
import type { SessionBootstrap } from "../types";
import { resolveSessionBootstrapFixture } from "../server/session-fixture";
import { buildCookieHeader, laravelJsonFetch } from "../utils/laravel-auth-api";
import { sanitizeDashboardUrl } from "../utils/dashboard-allowlist";

function initialsFromName(name: string): string {
  const parts = name.trim().split(/\s+/).filter(Boolean);
  if (parts.length === 0) return "?";
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
  return `${parts[0][0] ?? ""}${parts[parts.length - 1][0] ?? ""}`.toUpperCase();
}

export function mapBootstrapToPublicSession(bootstrap: SessionBootstrap): PublicSession {
  if (!bootstrap.authenticated || !bootstrap.user) {
    return { status: "anonymous" };
  }

  const dashboardUrl = sanitizeDashboardUrl(bootstrap.dashboard_url, "/");
  const landingRoute = sanitizeDashboardUrl(bootstrap.landing_route ?? bootstrap.dashboard_url, dashboardUrl);

  return {
    status: "authenticated",
    user: {
      id: bootstrap.user.id,
      displayName: bootstrap.user.name,
      email: bootstrap.user.email,
      initials: initialsFromName(bootstrap.user.name),
    },
    dashboardUrl,
    landingRoute,
    accountType: bootstrap.user.account_type ?? bootstrap.role ?? null,
    role: bootstrap.role ?? bootstrap.user.account_type ?? null,
    portalType: bootstrap.portal_type ?? null,
    agencyId: bootstrap.agency_id ?? null,
    agencyRole: bootstrap.agency_role ?? null,
    permissions: bootstrap.permissions ?? [],
    accountStatus: bootstrap.account_status ?? "active",
    emailVerified: bootstrap.email_verified ?? true,
    sessionUsable: bootstrap.session_usable ?? true,
    requiresPasswordChange: bootstrap.requires_password_change ?? false,
    requiresEmailVerification: bootstrap.requires_email_verification ?? false,
  };
}

export async function fetchSessionBootstrap(cookieHeader?: string): Promise<SessionBootstrap> {
  const headers: HeadersInit = {
    Accept: "application/json",
    "X-Requested-With": "XMLHttpRequest",
  };

  if (cookieHeader) {
    headers.Cookie = cookieHeader;
  }

  /*
   * Browser requests use the same-origin /laravel bridge.
   *
   * Next.js server components cannot fetch that relative browser URL.
   * During SSR, call Laravel's private server origin directly while
   * forwarding the incoming browser Cookie header unchanged.
   */
  if (typeof window === "undefined") {
    const laravelBase = (
      process.env.LARAVEL_URL ??
      process.env.NEXT_PUBLIC_LARAVEL_URL ??
      "http://127.0.0.1:8000"
    ).replace(/\/$/, "");

    try {
      const response = await fetch(`${laravelBase}/api/public/auth/session`, {
        method: "GET",
        headers,
        cache: "no-store",
        // Soft-nav to Traveler re-runs public layout; never block 30–40s on session lock.
        signal: AbortSignal.timeout(2500),
      });

      if (!response.ok) {
        return { authenticated: false };
      }

      return (await response.json()) as SessionBootstrap;
    } catch {
      return { authenticated: false };
    }
  }

  // Browser soft-nav handoff must not wait unboundedly on session lock (R6H: ~14s POST_CLICK tails).
  const result = await laravelJsonFetch<SessionBootstrap>("/api/public/auth/session", {
    method: "GET",
    headers,
    signal: AbortSignal.timeout(2500),
  });

  if (!result.ok) {
    return { authenticated: false };
  }

  return result.data;
}
export async function fetchSessionBootstrapFromCookies(
  cookies: Array<{ name: string; value: string }>,
): Promise<SessionBootstrap> {
  const fixture = resolveSessionBootstrapFixture(cookies);
  if (fixture !== null) {
    return fixture;
  }

  if (cookies.length === 0) {
    return { authenticated: false };
  }

  return fetchSessionBootstrap(buildCookieHeader(cookies));
}
