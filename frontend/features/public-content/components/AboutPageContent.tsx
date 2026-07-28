import Link from "next/link";
import { PageContainer } from "@/components/layout/PageContainer";
import { AnimatedFlightPath } from "@/components/motion/AnimatedFlightPath";
import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { SecondaryButton } from "@/components/ui/SecondaryButton";
import type { PublicPage } from "../types";
import { Breadcrumbs } from "./Breadcrumbs";
import { ContactDetailsCard } from "./ContactDetailsCard";
import { ContentCardGrid } from "./ContentCardGrid";
import { ContentRichText } from "./ContentRichText";
import { ContentSection } from "./ContentSection";
import { PublicPageHero } from "./PublicPageHero";

type AboutPageContentProps = {
  page: PublicPage;
};

export function AboutPageContent({ page }: AboutPageContentProps) {
  return (
    <PageContainer className="py-jp-4xl">
      <Breadcrumbs items={[{ label: "Home", href: "/" }, { label: "About us" }]} />

      <div className="mt-jp-xl space-y-jp-2xl">
        <div className="grid gap-jp-xl lg:grid-cols-[1.1fr_0.9fr] lg:items-center">
          <PublicPageHero hero={page.hero} id="about-page-heading" />
          <AnimatedFlightPath variant="hero" className="hidden lg:block" />
        </div>

        {page.sections.map((section) => (
          <ContentSection key={section.id} id={section.id} title={section.title}>
            {section.body ? <ContentRichText body={section.body} /> : null}
            {section.items?.length ? <ContentCardGrid items={section.items} columns={section.items.length >= 3 ? 3 : 2} /> : null}
          </ContentSection>
        ))}

        {page.contact ? <ContactDetailsCard contact={page.contact} /> : null}

        {page.cta ? (
          <div className="flex flex-wrap gap-3">
            {page.cta.primaryLabel && page.cta.primaryHref ? (
              <Link href={page.cta.primaryHref}>
                <PrimaryButton>{page.cta.primaryLabel}</PrimaryButton>
              </Link>
            ) : null}
            {page.cta.secondaryLabel && page.cta.secondaryHref ? (
              <Link href={page.cta.secondaryHref}>
                <SecondaryButton>{page.cta.secondaryLabel}</SecondaryButton>
              </Link>
            ) : null}
          </div>
        ) : null}
      </div>
    </PageContainer>
  );
}
