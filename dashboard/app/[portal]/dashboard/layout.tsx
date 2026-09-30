import { notFound } from "next/navigation";
import { PortalProvider } from "@/lib/portal-context";
import { DASHBOARD_PORTALS, isDashboardPortal, type DashboardPortal } from "@/lib/portal-path";
import { getDashboardSession } from "@/services/session-service";
import { SessionProvider } from "@/lib/session-context";

type Props = {
  children: React.ReactNode;
  params: Promise<{ portal: string }>;
};

export function generateStaticParams() {
  return DASHBOARD_PORTALS.map((portal) => ({ portal }));
}

export default async function PortalDashboardLayout({ children, params }: Props) {
  const { portal: portalParam } = await params;
  if (!isDashboardPortal(portalParam)) {
    notFound();
  }

  const portal = portalParam as DashboardPortal;
  let session = null;
  try {
    session = await getDashboardSession({ portal });
  } catch {
    session = null;
  }

  return (
    <PortalProvider portal={portal}>
      <SessionProvider session={session}>{children}</SessionProvider>
    </PortalProvider>
  );
}
