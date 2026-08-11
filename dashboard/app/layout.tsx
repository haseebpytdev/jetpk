import type { Metadata } from "next";
import { ThemeProvider } from "@/components/theme/ThemeProvider";
import { DashboardShell } from "@/layouts/dashboard-shell";
import { resolveDashboardPortalFromRequest } from "@/lib/dashboard-portal-server";
import { PortalProvider } from "@/lib/portal-context";
import { SessionProvider } from "@/lib/session-context";
import { themeBootstrapScript } from "@/lib/theme/theme-bootstrap-script";
import { getDashboardBranding } from "@/services/branding-service";
import { getDashboardSession } from "@/services/session-service";
import "./globals.css";

export const dynamic = "force-dynamic";

export const metadata: Metadata = {
  title: "JetPakistan Back Office",
  description: "JetPakistan admin and staff back-office dashboard",
  robots: { index: false, follow: false },
};

export default async function RootLayout({ children }: { children: React.ReactNode }) {
  const portal = await resolveDashboardPortalFromRequest();
  let session = null;
  let branding = null;
  try {
    session = await getDashboardSession({ portal });
    branding = await getDashboardBranding();
  } catch {
    session = null;
    branding = null;
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
