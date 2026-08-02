import { fetchSessionBootstrapFromCookies, mapBootstrapToPublicSession } from "@/features/auth/services/session-service";
import type { SessionBootstrap } from "@/features/auth/types";
import { resolveSessionBootstrapFixture } from "@/features/auth/server/session-fixture";
import {
  assertSessionUsable,
  redirectEmailVerificationRequired,
  redirectPasswordChangeRequired,
  redirectPendingOtp,
  redirectUnauthenticated,
  redirectWrongRole,
} from "@/features/auth/server/portal-access-shared";
import type { PublicSession } from "@/types/session";
import { cookies } from "next/headers";
import { redirect } from "next/navigation";

const CUSTOMER_ACCOUNT_TYPE = "customer";

export type CustomerPortalAccess = {
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
 * Laravel-authoritative guard for temporary Next.js customer portal routes.
 */
export async function requireCustomerPortalAccess(): Promise<CustomerPortalAccess> {
  const cookieStore = await cookies();
  const cookieList = cookieStore.getAll();
  const bootstrap = await loadBootstrap(cookieList);

  redirectPendingOtp(bootstrap);
  redirectUnauthenticated(bootstrap);
  assertSessionUsable(bootstrap);
  redirectPasswordChangeRequired(bootstrap);
  redirectEmailVerificationRequired(bootstrap);
  redirectWrongRole(bootstrap, new Set([CUSTOMER_ACCOUNT_TYPE]));

  return {
    session: mapBootstrapToPublicSession(bootstrap),
    bootstrap,
  };
}

/**
 * Layout-level customer portal guard: enforces auth without duplicating page data fetches.
 */
export async function requireCustomerPortalLayoutAccess(): Promise<PublicSession> {
  const { session } = await requireCustomerPortalAccess();
  return session;
}
