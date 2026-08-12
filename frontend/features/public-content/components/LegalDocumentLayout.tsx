import { PageContainer } from "@/components/layout/PageContainer";
import { Breadcrumbs } from "./Breadcrumbs";
import { ContentRichText } from "./ContentRichText";
import { TableOfContents } from "./TableOfContents";
import type { LegalDocument } from "../types";

type LegalDocumentLayoutProps = {
  document: LegalDocument;
  breadcrumbLabel: string;
};

export function LegalDocumentLayout({ document, breadcrumbLabel }: LegalDocumentLayoutProps) {
  return (
    <PageContainer className="py-jp-4xl">
      <Breadcrumbs
        items={[
          { label: "Home", href: "/" },
          { label: breadcrumbLabel },
        ]}
      />

      <div className="mt-jp-xl grid gap-jp-xl lg:grid-cols-[16rem_minmax(0,1fr)]">
        <aside className="lg:sticky lg:top-24 lg:self-start">
          <TableOfContents sections={document.sections} />
        </aside>

        <article className="jp-print-friendly min-w-0 rounded-jp-xl border border-jp-border bg-jp-surface p-jp-2xl shadow-jp-card">
          <header>
            <h1 className="font-sans text-jp-h2 font-bold text-jp-text">{document.title}</h1>
            {(document.effectiveDate || document.lastUpdated) && (
              <p className="mt-2 text-jp-sm text-jp-muted">
                {document.effectiveDate ? <span>Effective: {document.effectiveDate}</span> : null}
                {document.effectiveDate && document.lastUpdated ? <span className="mx-2">·</span> : null}
                {document.lastUpdated ? <span>Last updated: {document.lastUpdated}</span> : null}
              </p>
            )}
            {document.intro ? <p className="mt-4 text-jp-body text-jp-muted">{document.intro}</p> : null}
          </header>

          <div className="mt-jp-xl space-y-jp-xl">
            {document.sections.map((section) => (
              <section key={section.id} id={section.id} className="scroll-mt-28">
                <h2 className="font-sans text-jp-h3 font-semibold text-jp-text">{section.heading}</h2>
                <div className="mt-3">
                  <ContentRichText body={section.body} />
                </div>
              </section>
            ))}
          </div>
        </article>
      </div>
    </PageContainer>
  );
}
