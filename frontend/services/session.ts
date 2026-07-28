import { appConfig } from "@/lib/config";
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
 * Fixture session adapter for JP-FE-01 shell presentation states.
 * Replace with Laravel session bridge in a later auth integration phase.
 */
export const fixtureSessionAdapter: SessionAdapter = {
  async getSession(): Promise<PublicSession> {
    return resolvePreviewMode() === "logged-in"
      ? { status: "authenticated", user: fixtureUser }
      : { status: "anonymous" };
  },
};

export async function getPublicSession(
  preview?: SessionPreviewMode,
  adapter: SessionAdapter = fixtureSessionAdapter,
): Promise<PublicSession> {
  if (preview) {
    return preview === "logged-in"
      ? { status: "authenticated", user: fixtureUser }
      : { status: "anonymous" };
  }

  return adapter.getSession();
}
