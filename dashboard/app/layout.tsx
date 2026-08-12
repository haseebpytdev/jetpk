import type { Metadata } from "next";
import { redirect } from "next/navigation";
import { ThemeProvider } from "@/components/theme/ThemeProvider";
import { DashboardShell } from "@/layouts/dashboard-shell";
import { resolveDashboardPortalFromRequest } from "@/lib/dashboard-portal-server";
import { getDashboardMode } from "@/lib/preview";
import { PortalProvider } from "@/lib/portal-context";
import { SessionProvider } from "@/lib/session-context";
import { themeBootstrapScript } from "@/lib/theme/theme-bootstrap-script";
import { getDashboardBranding } from "@/services/branding-service";
import { getDashboardSession, type DashboardSessionSummary } from "@/services/session-service";
import "./globals.css";

export const dynamic = "force-dynamic";

export const metadata: Metadata = {
  title: "JetPakistan Back Office",
  description: "JetPakistan admin and staff back-office dashboard",
  robots: { index: false, follow: false },
};

const PUBLIC_ORIGIN = process.env.NEXT_PUBLIC_APP_URL ?? "https://jetpakistan.pk";

function sessionAllowedForPortal(session: DashboardSessionSummary, portal: "admin" | "staff"): boolean {
  if (!session.sessionUsable || session.unavailable) {
    return false;
  }
  if (session.denialReason === "forbidden" || session.denialReason === "unauthenticated") {
    return false;
  }

  const accountType = (session.accountType || "").toLowerCase();
  if (accountType === "customer" || accountType === "agent" || accountType === "agent_staff") {
    return false;
  }

  if (portal === "admin") {
    return (
      session.portalType === "admin" ||
      accountType === "platform_admin" ||
      accountType === "admin"
    );
  }

  return (
    session.portalType === "staff" ||
    accountType === "staff" ||
    accountType === "platform_staff" ||
    // Platform admins may open staff portal for coverage; still gated by Laravel APIs.
    session.portalType === "admin" ||
    accountType === "platform_admin" ||
    accountType === "admin"
  );
}

export default async function RootLayout({ children }: { children: React.ReactNode }) {
  const portal = await resolveDashboardPortalFromRequest();
  let session: DashboardSessionSummary | null = null;
  let branding = null;
  try {
    session = await getDashboardSession({ portal });
    branding = await getDashboardBranding();
  } catch {
    session = null;
    branding = null;
  }

  if (getDashboardMode() === "live") {
    if (!session || !sessionAllowedForPortal(session, portal)) {
      redirect(`${PUBLIC_ORIGIN}/access-denied`);
    }
  }

  return (
    <html lang="en" suppressHydrationWarning>
      <head>
        <script dangerouslySetInnerHTML={{ __html: themeBootstrapScript }} />
      </head>
      <body className="min-h-screen overflow-x-hidden font-sans antialiased">
        <PortalProvider portal={portal}>
          <ThemeProvider>
            <SessionProvider session={session}>
              <DashboardShell session={session} branding={branding}>{children}</DashboardShell>
            </SessionProvider>
          </ThemeProvider>
        </PortalProvider>
      </body>
    </html>
  );
}
