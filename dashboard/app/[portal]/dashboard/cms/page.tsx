import { redirect } from "next/navigation";

export const metadata = { title: "CMS — JetPakistan Dashboard" };

/**
 * Legacy CMS landing — compatibility redirect to Website → Homepage builder.
 * JP-ADMIN-CMS-03: no standalone CMS parent in primary nav.
 */
export default async function CmsOverviewPage({
  params,
}: {
  params: Promise<{ portal: string }>;
}) {
  const { portal } = await params;
  const safePortal = portal === "staff" ? "staff" : "admin";
  redirect(`/${safePortal}/dashboard/cms/sections`);
}
