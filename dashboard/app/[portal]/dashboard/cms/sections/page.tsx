import { CmsPageContent } from "@/features/cms/cms-page-content";

export const metadata = { title: "CMS Sections — JetPakistan Admin Preview" };

export default function CmsSectionsRoute({
  searchParams,
}: {
  searchParams: Promise<Record<string, string | string[] | undefined>>;
}) {
  return <CmsPageContent searchParams={searchParams} module="sections" />;
}
