import { PageContainer } from "@/components/layout/PageContainer";
import { CmsPageRenderer as CmsV2PageRenderer } from "@/features/cms-theme-v2";
import { Breadcrumbs } from "./Breadcrumbs";
import type { CustomClientPage } from "../services/custom-page-service";
import { customClientPageToPayload } from "../utils/cms-v2-bridge";

type CustomClientPageRendererProps = {
  page: CustomClientPage;
};

export function CustomClientPageRenderer({ page }: CustomClientPageRendererProps) {
  const payload = customClientPageToPayload(page);

  return (
    <PageContainer className="py-jp-4xl">
      <Breadcrumbs items={[{ label: "Home", href: "/" }, { label: page.title }]} />
      <div className="mt-jp-xl">
        <CmsV2PageRenderer page={payload} />
      </div>
    </PageContainer>
  );
}
