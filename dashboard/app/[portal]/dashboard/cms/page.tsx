import { CmsPageContent } from "@/features/cms/cms-page-content";

export const metadata = { title: "CMS — JetPakistan Dashboard" };

export default function CmsOverviewPage({
  searchParams,
}: {
  searchParams: Promise<Record<string, string | string[] | undefined>>;
}) {
  return <CmsPageContent searchParams={searchParams} module="overview" />;
}
