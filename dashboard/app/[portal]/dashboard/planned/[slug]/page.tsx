import { redirect } from "next/navigation";
import { DASHBOARD_PORTALS, type DashboardPortal } from "@/lib/portal-path";
import { plannedRedirectSlugs, resolvePlannedRedirect } from "@/lib/planned-route-redirects";

export function generateStaticParams(): Array<{ portal: DashboardPortal; slug: string }> {
  return DASHBOARD_PORTALS.flatMap((portal) => plannedRedirectSlugs.map((slug) => ({ portal, slug })));
}

export default async function PlannedRedirectPage({
  params,
}: {
  params: Promise<{ portal: DashboardPortal; slug: string }>;
}) {
  const { portal, slug } = await params;
  redirect(resolvePlannedRedirect(portal, slug));
}
