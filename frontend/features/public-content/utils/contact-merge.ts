import type { ContactDetails } from "../types";
import { SITE_CONTACT_FIXTURE } from "../fixtures/site-contact";
import { allowContentFixtures } from "./content-policy";

function normalizePublicWebsite(website: string): string {
  const trimmed = website.trim();
  if (trimmed === "") {
    return "";
  }

  return trimmed.replace(/^https?:\/\/(www\.)?jetpakistan\.com\/?$/i, "https://jetpakistan.pk");
}

export function mergeContactDetails(primary: ContactDetails | null | undefined): ContactDetails {
  const normalize = (contact: ContactDetails): ContactDetails => ({
    ...contact,
    website: normalizePublicWebsite(contact.website ?? ""),
  });

  if (!primary) {
    return allowContentFixtures()
      ? normalize(SITE_CONTACT_FIXTURE)
      : {
          phone: "",
          phone_e164: "",
          email: "",
          whatsapp: "",
          website: "",
          office: "",
          hours: "",
          company_legal_name: "",
        };
  }

  if (!allowContentFixtures()) {
    return normalize(primary);
  }

  return normalize({
    ...SITE_CONTACT_FIXTURE,
    ...Object.fromEntries(Object.entries(primary).filter(([, value]) => value !== "")),
  } as ContactDetails);
}
