import { notFound } from "next/navigation";
import { PortalProvider } from "@/lib/portal-context";
import { DASHBOARD_PORTALS, isDashboardPortal } from "@/lib/portal-path";

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

  return <PortalProvider portal={portalParam}>{children}</PortalProvider>;
}
