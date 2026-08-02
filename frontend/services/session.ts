import { appConfig } from "@/lib/config";
import { fetchSessionBootstrap, fetchSessionBootstrapFromCookies, mapBootstrapToPublicSession } from "@/features/auth/services/session-service";
import type { PublicSession, SessionAdapter, SessionPreviewMode } from "@/types/session";

const fixtureUser = {
  id: "fixture-user-1",
  displayName: "Ayesha Khan",
  email: "ayesha.khan@example.com",
  initials: "AK",
};

function isPreviewAuthorityAllowed(): boolean {
  return process.env.NODE_ENV !== "production";
}

function resolvePreviewMode(override?: SessionPreviewMode): SessionPreviewMode {
  if (override) return override;
  return appConfig.sessionPreview === "logged-in" ? "logged-in" : "logged-out";
}

function buildPreviewSession(mode: SessionPreviewMode): PublicSession {
  if (mode !== "logged-in") {
    return { status: "anonymous" };
  }

  return {
    status: "authenticated",
    user: fixtureUser,
    dashboardUrl: "/customer/bookings",
    landingRoute: "/customer/bookings",
    accountType: "customer",
    role: "customer",
    portalType: "customer",
    agencyId: null,
    agencyRole: null,
    permissions: [],
    accountStatus: "active",
    emailVerified: true,
    sessionUsable: true,
    requiresPasswordChange: false,
    requiresEmailVerification: false,
  };
}

/**
 * Fixture session adapter for isolated UI preview (`NEXT_PUBLIC_SESSION_PREVIEW=logged-in`).
 * Never authoritative in production builds.
 */
export const fixtureSessionAdapter: SessionAdapter = {
  async getSession(): Promise<PublicSession> {
    return buildPreviewSession(resolvePreviewMode());
  },
};

export const laravelSessionAdapter: SessionAdapter = {
  async getSession(): Promise<PublicSession> {
    try {
      const bootstrap = await fetchSessionBootstrap();
      return mapBootstrapToPublicSession(bootstrap);
    } catch {
      return { status: "anonymous" };
    }
  },
};

export async function getPublicSession(
  preview?: SessionPreviewMode,
  adapter?: SessionAdapter,
): Promise<PublicSession> {
  if (preview && isPreviewAuthorityAllowed()) {
    return buildPreviewSession(preview);
  }

  if (isPreviewAuthorityAllowed() && appConfig.sessionPreview === "logged-in") {
    return fixtureSessionAdapter.getSession();
  }

  if (typeof window === "undefined") {
    const { cookies } = await import("next/headers");
    const cookieStore = await cookies();
    const bootstrap = await fetchSessionBootstrapFromCookies(cookieStore.getAll());
    return mapBootstrapToPublicSession(bootstrap);
  }

  const resolvedAdapter = adapter ?? laravelSessionAdapter;
  return resolvedAdapter.getSession();
}
