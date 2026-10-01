import { notFound, redirect } from "next/navigation";
import { PortalProvider } from "@/lib/portal-context";
import { DASHBOARD_PORTALS, isDashboardPortal, type DashboardPortal } from "@/lib/portal-path";
import { getDashboardMode } from "@/lib/preview";
import {
  DashboardPortalSessionError,
  getDashboardSession,
  getRequiredDashboardPortalSession,
} from "@/services/session-service";
import { SessionProvider } from "@/lib/session-context";
import { ForbiddenState } from "@/components/ui/data-source-status";
import { PageContainer } from "@/components/ui/page-layout";
import { ReadOnlyServiceError } from "@/lib/read-only/read-only-service";

type Props = {
  children: React.ReactNode;
  params: Promise<{ portal: string }>;
};

export function generateStaticParams() {
  return DASHBOARD_PORTALS.map((portal) => ({ portal }));
}

function PortalAccessDenied({ portal }: { portal: DashboardPortal }) {
  return (
    <PageContainer>
      <ForbiddenState resource={`${portal} dashboard`} />
    </PageContainer>
  );
}

export default async function PortalDashboardLayout({ children, params }: Props) {
  const { portal: portalParam } = await params;
  if (!isDashboardPortal(portalParam)) {
    notFound();
  }

  const portal = portalParam as DashboardPortal;

  if (getDashboardMode() === "live") {
    try {
      const session = await getRequiredDashboardPortalSession(portal);
      return (
        <PortalProvider portal={portal}>
          <SessionProvider session={session}>{children}</SessionProvider>
        </PortalProvider>
      );
    } catch (error) {
      if (error instanceof DashboardPortalSessionError && error.code === "unauthenticated") {
        redirect("/login");
      }
      if (error instanceof ReadOnlyServiceError && error.envelope.error.code === "unauthenticated") {
        redirect("/login");
      }
      return (
        <PortalProvider portal={portal}>
          <PortalAccessDenied portal={portal} />
        </PortalProvider>
      );
    }
  }

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
