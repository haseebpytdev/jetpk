import type { Metadata } from "next";
import { PageContainer } from "@/components/layout/PageContainer";
import { Breadcrumbs, PublicPageHero, publicSeoToMetadata } from "@/features/public-content";
import { SaudiVisaLookupClient } from "@/features/visa/SaudiVisaLookupClient";

export async function generateMetadata(): Promise<Metadata> {
  return publicSeoToMetadata(
    {
      title: "Saudi Visa Lookup — JetPakistan",
      description: "Look up a Saudi visa via JetPakistan using the official MOFA Visa Platform with human captcha entry.",
      robots: "noindex,nofollow",
    },
    "/visa/saudi",
  );
}

export default function SaudiVisaPage() {
  return (
    <PageContainer className="py-jp-4xl">
      <Breadcrumbs
        items={[
          { label: "Home", href: "/" },
          { label: "Visa Services", href: "/visa" },
          { label: "Saudi Arabia" },
        ]}
      />
      <div className="mt-jp-xl space-y-jp-2xl">
        <PublicPageHero
          hero={{
            kicker: "Saudi Arabia",
            title: "Saudi Visa Lookup",
            description: "Enter your details and the MOFA image code. Captcha is always solved by a human.",
          }}
          id="saudi-visa-heading"
        />
        <SaudiVisaLookupClient />
      </div>
    </PageContainer>
  );
}
