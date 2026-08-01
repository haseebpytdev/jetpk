import { PageContainer } from "@/components/layout/PageContainer";
import { CmsPageRenderer as CmsV2PageRenderer } from "@/features/cms-theme-v2";
import { Breadcrumbs } from "./Breadcrumbs";
import type { CmsPublicPage } from "../types";
import { cmsPublicPageToPayload } from "../utils/cms-v2-bridge";

type CmsPageRendererProps = {
  page: CmsPublicPage;
};

export function CmsPageRenderer({ page }: CmsPageRendererProps) {
  const payload = cmsPublicPageToPayload(page);

  return (
    <PageContainer className="py-jp-4xl">
      <Breadcrumbs
        items={[
          { label: "Home", href: "/" },
          { label: page.title },
        ]}
      />
      <div className="mt-jp-xl">
        <CmsV2PageRenderer page={payload} />
      </div>
    </PageContainer>
  );
}
