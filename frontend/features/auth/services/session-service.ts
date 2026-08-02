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
  const headers: HeadersInit = {};
  if (cookieHeader) {
    headers.Cookie = cookieHeader;
  }

  const result = await laravelJsonFetch<SessionBootstrap>("/api/public/auth/session", {
    method: "GET",
    headers,
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
