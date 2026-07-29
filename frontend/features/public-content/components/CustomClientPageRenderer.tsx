import { PageContainer } from "@/components/layout/PageContainer";
import { Breadcrumbs } from "./Breadcrumbs";
import { PublicPageHero } from "./PublicPageHero";
import { ContentSection } from "./ContentSection";
import { ContentRichText } from "./ContentRichText";
import type { CustomClientPage } from "../services/custom-page-service";

type CustomClientPageRendererProps = {
  page: CustomClientPage;
};

export function CustomClientPageRenderer({ page }: CustomClientPageRendererProps) {
  return (
    <PageContainer className="py-jp-4xl">
      <Breadcrumbs items={[{ label: "Home", href: "/" }, { label: page.title }]} />
      <div className="mt-jp-xl space-y-jp-xl">
        <PublicPageHero hero={{ title: page.title, description: page.subtitle }} id="custom-page-heading" />
        {page.sections.map((section) => (
          <ContentSection key={section.id} id={section.id} title={section.heading}>
            {section.eyebrow ? <p className="text-jp-xs font-semibold uppercase tracking-wide text-jp-muted">{section.eyebrow}</p> : null}
            <ContentRichText body={section.body ?? ""} />
          </ContentSection>
        ))}
      </div>
    </PageContainer>
  );
}
