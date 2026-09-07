import type { Metadata } from "next";
import { PageContainer } from "@/components/layout/PageContainer";
import { PublicSectionHeader } from "@/features/public-visual";
import {
  Breadcrumbs,
  ContactDetailsCard,
  FaqService,
  PublicPageHero,
  SupportContentService,
  publicSeoToMetadata,
} from "@/features/public-content";
import { SupportContactIsland } from "@/features/public-content/components/SupportContactIsland";
import { SupportFaqPreview } from "@/features/public-content/components/SupportFaqPreview";
import { SupportTopicSearch } from "@/features/public-content/components/SupportTopicSearch";

export const revalidate = 300;
export const dynamic = "force-static";

export async function generateMetadata(): Promise<Metadata> {
  const content = await SupportContentService.getSupportPage();
  return publicSeoToMetadata(content.seo, "/support");
}

/**
 * Support RSC shell: heading, FAQ preview and contact facts are server HTML so
 * the page remains useful before Turnstile/form hydration.
 */
export default async function SupportPage() {
  const [content, faqContent] = await Promise.all([
    SupportContentService.getSupportPage(),
    FaqService.getFaqPage(),
  ]);

  const faqTeaser = content.faqTeaser;
  const faqItems = faqContent.categories.flatMap((category) =>
    category.items.map((item) => ({
      id: item.id,
      question: item.question,
      answer: item.answer,
    })),
  );

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
          <section aria-labelledby="support-faq-heading">
            <div id="support-faq-heading">
              <PublicSectionHeader
                title="Frequently Asked Questions"
                subtitle={
                  faqTeaser?.body ||
                  "Quick answers to common booking, baggage, payment and refund questions."
                }
                ctaText={faqTeaser?.linkLabel || "View full help centre"}
                ctaUrl={faqTeaser?.linkHref || "/faq"}
              />
            </div>
            <SupportFaqPreview items={faqItems} />
          </section>

          <section className="space-y-jp-lg" aria-labelledby="support-contact-heading">
            <div id="support-contact-heading">
              <PublicSectionHeader
                title="Contact Us"
                subtitle="Multiple ways to reach our support team."
              />
            </div>
            <ContactDetailsCard contact={content.contact} />
          </section>
        </div>

        <section
          className="rounded-jp-xl border border-jp-border bg-jp-surface p-jp-2xl shadow-jp-card"
          aria-labelledby="support-form-heading"
        >
          <h2 id="support-form-heading" className="text-jp-h3 font-semibold text-jp-text">
            Submit a support request
          </h2>
          <p className="mt-2 text-jp-sm text-jp-muted">
            Tell us what you need and our team will respond shortly. For urgent booking status,
            include your booking reference.
          </p>
          <div className="mt-6">
            <SupportContactIsland />
          </div>
        </section>
      </div>
    </PageContainer>
  );
}
