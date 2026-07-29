import type { PublicConfig } from "../services/public-config-service";
import type { ContactDetails } from "../types";

type SeoJsonLdProps = {
  config: PublicConfig | null;
  contact?: ContactDetails;
};

export function SeoJsonLd({ config, contact }: SeoJsonLdProps) {
  const resolvedContact = contact ?? config?.contact;
  const appUrl = config?.app_url ?? process.env.NEXT_PUBLIC_APP_URL ?? "https://www.jetpakistan.com";

  const organization = {
    "@context": "https://schema.org",
    "@type": "TravelAgency",
    name: config?.brand_name ?? "JetPakistan",
    url: appUrl,
    logo: `${appUrl}/favicon.ico`,
    email: resolvedContact?.email,
    telephone: resolvedContact?.phone_e164 || resolvedContact?.phone,
    address: resolvedContact?.office,
  };

  const website = {
    "@context": "https://schema.org",
    "@type": "WebSite",
    name: config?.brand_name ?? "JetPakistan",
    url: appUrl,
  };

  return (
    <>
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(organization) }} />
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(website) }} />
    </>
  );
}
