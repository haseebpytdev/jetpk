import { CmsPageContent } from "@/features/cms/cms-page-content";

export const metadata = { title: "CMS Assets — JetPakistan Admin Preview" };

export default function CmsAssetsRoute({
  searchParams,
}: {
  searchParams: Promise<Record<string, string | string[] | undefined>>;
}) {
  return <CmsPageContent searchParams={searchParams} module="assets" />;
}
