import type { Metadata } from "next";
import Link from "next/link";
import { PageContainer } from "@/components/layout/PageContainer";
import { PublicSectionHeader } from "@/features/public-visual";
import {
  Breadcrumbs,
  ContactDetailsCard,
  PublicPageHero,
  SupportContentService,
  publicSeoToMetadata,
} from "@/features/public-content";
import { SupportContactIsland } from "@/features/public-content/components/SupportContactIsland";
import { SupportTopicSearch } from "@/features/public-content/components/SupportTopicSearch";

export const revalidate = 300;
export const dynamic = "force-static";

export async function generateMetadata(): Promise<Metadata> {
  const content = await SupportContentService.getSupportPage();
  return publicSeoToMetadata(content.seo, "/support");
}

/**
 * Support RSC shell: heading and contact facts are server HTML so soft-nav
 * usable markers do not wait on Turnstile/form hydration.
 */
export default async function SupportPage() {
  const content = await SupportContentService.getSupportPage();
  const faqTeaser = content.faqTeaser;

  return (
    <PageContainer className="py-jp-4xl">
      <Breadcrumbs items={[{ label: "Home", href: "/" }, { label: "Support" }]} />
      <div className="mt-jp-xl space-y-jp-3xl">
        <PublicPageHero
          hero={{
            kicker: content.hero.kicker,
            title: content.hero.title || "We're Here to Help",
            description: content.hero.description,
          }}
          id="support-page-heading"
          variant="support"
        >
          <SupportTopicSearch topics={content.topics} />
        </PublicPageHero>

        <div className="grid gap-jp-xl lg:grid-cols-[1.1fr_0.9fr]">
          {faqTeaser ? (
            <section>
              <PublicSectionHeader title="Frequently Asked Questions" ctaText={faqTeaser.linkLabel} ctaUrl={faqTeaser.linkHref} />
              {faqTeaser.body ? <p className="mt-jp-lg text-jp-sm text-jp-muted">{faqTeaser.body}</p> : null}
              <p className="mt-4">
                <Link href={faqTeaser.linkHref} className="text-jp-sm font-semibold text-jp-primary hover:underline">
                  {faqTeaser.linkLabel}
                </Link>
              </p>
            </section>
          ) : null}

          <section className="space-y-jp-lg">
            <PublicSectionHeader title="Contact Us" subtitle="Multiple ways to reach our support team." />
            <ContactDetailsCard contact={content.contact} />
          </section>
        </div>

        <section className="rounded-jp-xl border border-jp-border bg-jp-surface p-jp-2xl shadow-jp-card" aria-labelledby="support-form-heading">
          <h2 id="support-form-heading" className="text-jp-h3 font-semibold text-jp-text">
            Submit a support request
          </h2>
          <p className="mt-2 text-jp-sm text-jp-muted">
            Tell us what you need and our team will respond shortly. For urgent booking status, include your booking reference.
          </p>
          <div className="mt-6">
            <SupportContactIsland />
          </div>
        </section>
      </div>
    </PageContainer>
  );
}
