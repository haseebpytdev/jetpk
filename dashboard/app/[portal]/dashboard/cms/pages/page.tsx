import { CmsPageContent } from "@/features/cms/cms-page-content";

export const metadata = { title: "CMS Pages — JetPakistan Admin Preview" };

export default function CmsPagesRoute({
  searchParams,
}: {
  searchParams: Promise<Record<string, string | string[] | undefined>>;
}) {
  return <CmsPageContent searchParams={searchParams} module="pages" />;
}
