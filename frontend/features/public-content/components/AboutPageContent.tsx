import Link from "next/link";
import { PageContainer } from "@/components/layout/PageContainer";
import { AnimatedFlightPath } from "@/components/motion/AnimatedFlightPath";
import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { SecondaryButton } from "@/components/ui/SecondaryButton";
import { PublicSectionHeader } from "@/features/public-visual";
import type { PublicPage } from "../types";
import { Breadcrumbs } from "./Breadcrumbs";
import { ContactDetailsCard } from "./ContactDetailsCard";
import { ContentCardGrid } from "./ContentCardGrid";
import { ContentRichText } from "./ContentRichText";
import { ContentSection } from "./ContentSection";

type AboutPageContentProps = {
  page: PublicPage;
};

export function AboutPageContent({ page }: AboutPageContentProps) {
  return (
    <PageContainer className="py-jp-4xl">
      <Breadcrumbs items={[{ label: "Home", href: "/" }, { label: "About us" }]} />

      <div className="mt-jp-xl space-y-jp-3xl">
        <div className="grid gap-jp-xl lg:grid-cols-[1.05fr_0.95fr] lg:items-center">
          <header className="max-w-2xl">
            {page.hero.kicker ? (
              <p className="text-jp-xs font-semibold uppercase tracking-[0.16em] text-jp-primary">{page.hero.kicker}</p>
            ) : null}
            <h1 id="about-page-heading" className="mt-3 font-display text-jp-h1 font-bold text-jp-text">
              {page.hero.title}
            </h1>
            {page.hero.description ? (
              <p className="mt-4 text-jp-body leading-relaxed text-jp-muted">{page.hero.description}</p>
            ) : null}
          </header>
          <AnimatedFlightPath variant="hero" className="hidden lg:block" />
        </div>

        {page.sections.map((section) => (
          <ContentSection key={section.id} id={section.id} title={section.title}>
            {section.body ? <ContentRichText body={section.body} /> : null}
            {section.items?.length ? (
              <ContentCardGrid items={section.items} columns={section.items.length >= 3 ? 3 : 2} />
            ) : null}
          </ContentSection>
        ))}

        {page.contact ? <ContactDetailsCard contact={page.contact} /> : null}

        {page.cta ? (
          <section className="rounded-jp-xl bg-gradient-to-r from-jp-primary to-jp-primary-hover p-jp-2xl text-white">
            <PublicSectionHeader
              title="Let's explore the world, together"
              subtitle="Book your next trip with JetPakistan."
              className="!text-white [&_h2]:text-white [&_p]:text-white/85"
            />
            <div className="mt-6 flex flex-wrap gap-3">
              {page.cta.primaryLabel && page.cta.primaryHref ? (
                <Link href={page.cta.primaryHref}>
                  <PrimaryButton className="bg-white text-jp-primary hover:bg-white/90">
                    {page.cta.primaryLabel}
                  </PrimaryButton>
                </Link>
              ) : null}
              {page.cta.secondaryLabel && page.cta.secondaryHref ? (
                <Link href={page.cta.secondaryHref}>
                  <SecondaryButton className="border-white text-white hover:bg-white/10">
                    {page.cta.secondaryLabel}
                  </SecondaryButton>
                </Link>
              ) : null}
            </div>
          </section>
        ) : null}
      </div>
    </PageContainer>
  );
}
