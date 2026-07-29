import { fetchSessionBootstrapFromCookies, mapBootstrapToPublicSession } from "@/features/auth/services/session-service";
import type { SessionBootstrap } from "@/features/auth/types";
import { resolveSessionBootstrapFixture } from "@/features/auth/server/session-fixture";
import { sanitizeDashboardUrl } from "@/features/auth/utils/dashboard-allowlist";
import type { PublicSession } from "@/types/session";
import { cookies } from "next/headers";
import { redirect } from "next/navigation";

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

  if (!bootstrap.authenticated || !bootstrap.user) {
    redirect("/login");
  }

  const accountType = bootstrap.user.account_type ?? bootstrap.role ?? null;
  if (!accountType || !AGENT_ACCOUNT_TYPES.has(accountType)) {
    const destination = sanitizeDashboardUrl(bootstrap.dashboard_url, "/access-denied");
    redirect(destination);
  }

  return {
    session: mapBootstrapToPublicSession(bootstrap),
    bootstrap,
  };
}
