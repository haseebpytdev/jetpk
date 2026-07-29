import { SITE_CONTACT_FIXTURE } from "../fixtures/site-contact";
import type { ContactDetails } from "../types";
import { allowContentFixtures } from "../utils/content-policy";
import { fetchSiteContactFromLaravel, mergeContactDetails } from "../utils/laravel-api";

export const SiteContactService = {
  async getContactDetails(): Promise<ContactDetails> {
    const remote = await fetchSiteContactFromLaravel();
    if (!remote && !allowContentFixtures()) {
      return {
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

    return mergeContactDetails(remote ?? (allowContentFixtures() ? SITE_CONTACT_FIXTURE : null));
  },
};
