import { CmsPageContent } from "@/features/cms/cms-page-content";

export const metadata = { title: "CMS Banners — JetPakistan Admin Preview" };

export default function CmsBannersRoute({
  searchParams,
}: {
  searchParams: Promise<Record<string, string | string[] | undefined>>;
}) {
  return <CmsPageContent searchParams={searchParams} module="banners" />;
}
