import { PageContainer } from "@/components/layout/PageContainer";
import { Breadcrumbs } from "./Breadcrumbs";
import { PublicPageHero } from "./PublicPageHero";
import type { CmsPublicPage } from "../types";

type CmsPageRendererProps = {
  page: CmsPublicPage;
};

export function CmsPageRenderer({ page }: CmsPageRendererProps) {
  return (
    <PageContainer className="py-jp-4xl">
      <Breadcrumbs
        items={[
          { label: "Home", href: "/" },
          { label: page.title },
        ]}
      />
      <div className="mt-jp-xl space-y-jp-xl">
        <PublicPageHero hero={{ title: page.title, description: page.subtitle }} />
        <article
          className="prose-jp rounded-jp-xl border border-jp-border bg-jp-surface p-jp-2xl text-jp-body text-jp-muted shadow-jp-card"
          dangerouslySetInnerHTML={{ __html: page.bodyHtml }}
        />
      </div>
    </PageContainer>
  );
}
