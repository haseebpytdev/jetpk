import { fetchSessionBootstrapFromCookies } from "@/features/auth/services/session-service";
import type { SessionBootstrap } from "@/features/auth/types";
import { resolveSessionBootstrapFixture } from "@/features/auth/server/session-fixture";
import { redirectUnauthenticated } from "@/features/auth/server/portal-access-shared";
import { sanitizeDashboardUrl } from "@/features/auth/utils/dashboard-allowlist";
import { cookies } from "next/headers";
import { redirect } from "next/navigation";

async function loadBootstrap(cookieList: Array<{ name: string; value: string }>): Promise<SessionBootstrap> {
  const fixture = resolveSessionBootstrapFixture(cookieList);
  if (fixture !== null) {
    return fixture;
  }

  return fetchSessionBootstrapFromCookies(cookieList);
}

/**
 * Laravel-authoritative guard for the Next.js force-password page.
 * Avoids redirect loops: this route stays accessible while change is required.
 */
export async function requireForcePasswordPageAccess(): Promise<SessionBootstrap> {
  const cookieStore = await cookies();
  const cookieList = cookieStore.getAll();
  const bootstrap = await loadBootstrap(cookieList);

  redirectUnauthenticated(bootstrap);

  if (!bootstrap.requires_password_change) {
    const destination = sanitizeDashboardUrl(bootstrap.dashboard_url ?? bootstrap.landing_route, "/");
    redirect(destination);
  }

  return bootstrap;
}
