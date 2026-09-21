import type { PublicConfig } from "../services/public-config-service";
import type { ContactDetails } from "../types";
import { hasVisibleContactFacts } from "../utils/contact-facts";

type SeoJsonLdProps = {
  config: PublicConfig | null;
  contact?: ContactDetails;
};

/**
 * TravelAgency + WebSite JSON-LD from PublicConfig.
 * Contact fields mirror visible SiteContact / Support facts when configured.
 */
export function SeoJsonLd({ config, contact }: SeoJsonLdProps) {
  const resolvedContact = contact ?? config?.contact;
  const appUrl = (config?.app_url ?? process.env.NEXT_PUBLIC_APP_URL ?? "https://jetpakistan.pk").replace(
    /\/$/,
    "",
  );

  const logo =
    config?.logo_url?.trim() ||
    config?.favicon_url?.trim() ||
    `${appUrl}/favicon.ico`;

  const organization: Record<string, unknown> = {
    "@context": "https://schema.org",
    "@type": "TravelAgency",
    name: config?.brand_name?.trim() || "JetPakistan",
    url: appUrl,
    logo,
  };

  if (hasVisibleContactFacts(resolvedContact)) {
    const email = resolvedContact?.email?.trim();
    const telephone = (resolvedContact?.phone_e164 || resolvedContact?.phone || "").trim();
    const address = resolvedContact?.office?.trim();
    if (email) organization.email = email;
    if (telephone) organization.telephone = telephone;
    if (address) organization.address = address;
  }

  const sameAs = (config?.social_links ?? [])
    .map((link) => link.href?.trim())
    .filter((href): href is string => Boolean(href));

  if (sameAs.length > 0) {
    organization.sameAs = sameAs;
  }

  const website = {
    "@context": "https://schema.org",
    "@type": "WebSite",
    name: config?.brand_name?.trim() || "JetPakistan",
    url: appUrl,
  };

  return (
    <>
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(organization) }} />
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(website) }} />
    </>
  );
}
