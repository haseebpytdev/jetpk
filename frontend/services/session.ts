import { appConfig } from "@/lib/config";
import { fetchSessionBootstrap, fetchSessionBootstrapFromCookies, mapBootstrapToPublicSession } from "@/features/auth/services/session-service";
import type { PublicSession, SessionAdapter, SessionPreviewMode } from "@/types/session";

const fixtureUser = {
  id: "fixture-user-1",
  displayName: "Ayesha Khan",
  email: "ayesha.khan@example.com",
  initials: "AK",
};

function resolvePreviewMode(override?: SessionPreviewMode): SessionPreviewMode {
  if (override) return override;
  return appConfig.sessionPreview === "logged-in" ? "logged-in" : "logged-out";
}

/**
 * Fixture session adapter for isolated UI preview (`NEXT_PUBLIC_SESSION_PREVIEW=logged-in`).
 */
export const fixtureSessionAdapter: SessionAdapter = {
  async getSession(): Promise<PublicSession> {
    return resolvePreviewMode() === "logged-in"
      ? {
          status: "authenticated",
          user: fixtureUser,
          dashboardUrl: "/customer/bookings",
          accountType: "customer",
          role: "customer",
        }
      : { status: "anonymous" };
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
  if (preview) {
    return preview === "logged-in"
      ? {
          status: "authenticated",
          user: fixtureUser,
          dashboardUrl: "/customer/bookings",
          accountType: "customer",
          role: "customer",
        }
      : { status: "anonymous" };
  }

  if (appConfig.sessionPreview === "logged-in") {
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
