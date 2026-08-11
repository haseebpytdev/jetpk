import { redirect } from "next/navigation";
import type { DashboardPortal } from "@/lib/portal-path";
import { resolvePlannedRedirect } from "@/lib/planned-route-redirects";

export const dynamic = "force-dynamic";

export default async function PlannedRedirectPage({
  params,
}: {
  params: Promise<{ portal: DashboardPortal; slug: string }>;
}) {
  const { portal, slug } = await params;
  redirect(resolvePlannedRedirect(portal, slug));
}
