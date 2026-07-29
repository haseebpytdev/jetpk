import { fetchSessionBootstrapFromCookies, mapBootstrapToPublicSession } from "@/features/auth/services/session-service";
import type { SessionBootstrap } from "@/features/auth/types";
import { resolveSessionBootstrapFixture } from "@/features/auth/server/session-fixture";
import { sanitizeDashboardUrl } from "@/features/auth/utils/dashboard-allowlist";
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
 * Redirects unauthenticated users to login and non-customers to their Laravel dashboard_url.
 */
export async function requireCustomerPortalAccess(): Promise<CustomerPortalAccess> {
  const cookieStore = await cookies();
  const cookieList = cookieStore.getAll();
  const bootstrap = await loadBootstrap(cookieList);

  if (!bootstrap.authenticated || !bootstrap.user) {
    redirect("/login");
  }

  const accountType = bootstrap.user.account_type ?? bootstrap.role ?? null;
  if (accountType !== CUSTOMER_ACCOUNT_TYPE) {
    const destination = sanitizeDashboardUrl(bootstrap.dashboard_url, "/access-denied");
    const customerPaths = new Set(["/customer", "/customer/dashboard", "/customer/bookings"]);

    if (customerPaths.has(destination.split("?")[0] ?? "")) {
      redirect("/access-denied");
    }

    redirect(destination);
  }

  return {
    session: mapBootstrapToPublicSession(bootstrap),
    bootstrap,
  };
}
