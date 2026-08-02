import { fetchSessionBootstrapFromCookies, mapBootstrapToPublicSession } from "@/features/auth/services/session-service";
import type { SessionBootstrap } from "@/features/auth/types";
import { resolveSessionBootstrapFixture } from "@/features/auth/server/session-fixture";
import {
  assertSessionUsable,
  redirectPasswordChangeRequired,
  redirectPendingOtp,
  redirectUnauthenticated,
  redirectWrongRole,
} from "@/features/auth/server/portal-access-shared";
import type { PublicSession } from "@/types/session";
import { cookies } from "next/headers";

const AGENT_ACCOUNT_TYPES = new Set(["agent", "agent_staff"]);

export type AgentPortalAccess = {
  session: PublicSession;
  bootstrap: SessionBootstrap;
};

async function loadBootstrap(cookieList: Array<{ name: string; value: string }>): Promise<SessionBootstrap> {
  const fixture = resolveSessionBootstrapFixture(cookieList);
  if (fixture !== null) {
    return fixture;
  }

  return fetchSessionBootstrapFromCookies(cookieList);
}

/**
 * Laravel-authoritative guard for Next.js agent portal routes.
 */
export async function requireAgentPortalAccess(): Promise<AgentPortalAccess> {
  const cookieStore = await cookies();
  const cookieList = cookieStore.getAll();
  const bootstrap = await loadBootstrap(cookieList);

  redirectPendingOtp(bootstrap);
  redirectUnauthenticated(bootstrap);
  assertSessionUsable(bootstrap);
  redirectPasswordChangeRequired(bootstrap);
  redirectWrongRole(bootstrap, AGENT_ACCOUNT_TYPES);

  return {
    session: mapBootstrapToPublicSession(bootstrap),
    bootstrap,
  };
}

/**
 * Layout-level agent portal guard.
 */
export async function requireAgentPortalLayoutAccess(): Promise<PublicSession> {
  const { session } = await requireAgentPortalAccess();
  return session;
}
