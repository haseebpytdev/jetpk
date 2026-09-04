import type { Metadata } from "next";
import { PageContainer } from "@/components/layout/PageContainer";
import {
  Breadcrumbs,
  ContactDetailsCard,
  ContactForm,
  PublicPageHero,
  SiteContactService,
  publicSeoToMetadata,
} from "@/features/public-content";

export const revalidate = 300;
export const dynamic = "force-static";

export async function generateMetadata(): Promise<Metadata> {
  return publicSeoToMetadata(
    {
      title: "Contact — JetPakistan",
      description: "Contact JetPakistan by phone, WhatsApp, email, or secure message form.",
      robots: "index,follow",
    },
    "/contact",
  );
}

export default async function ContactPage() {
  const contact = await SiteContactService.getContactDetails();

  return (
    <PageContainer className="py-jp-4xl">
      <Breadcrumbs items={[{ label: "Home", href: "/" }, { label: "Contact" }]} />
      <div className="mt-jp-xl space-y-jp-2xl">
        <PublicPageHero
          hero={{
            kicker: "Contact",
            title: "Get in touch with JetPakistan",
            description: "Reach our team for booking help, partnership questions, or general inquiries.",
          }}
          id="contact-page-heading"
          variant="support"
        />

        <div className="grid gap-jp-xl lg:grid-cols-2">
          <ContactDetailsCard contact={contact} />
          <section className="rounded-jp-xl border border-jp-border bg-jp-surface p-jp-2xl shadow-jp-card" aria-labelledby="contact-form-heading">
            <h2 id="contact-form-heading" className="text-jp-h3 font-semibold text-jp-text">
              Send us a message
            </h2>
            <p className="mt-2 text-jp-sm text-jp-muted">
              Messages are delivered to our support team through the same Laravel ticket workflow used on the Support page.
            </p>
            <div className="mt-6">
              <ContactForm formType="contact" />
            </div>
          </section>
        </div>
      </div>
    </PageContainer>
  );
}
