import { SITE_CONTACT_FIXTURE } from "../fixtures/site-contact";
import type { ContactDetails } from "../types";
import { fetchSiteContactFromLaravel, mergeContactDetails } from "../utils/laravel-api";

export const SiteContactService = {
  async getContactDetails(): Promise<ContactDetails> {
    const remote = await fetchSiteContactFromLaravel();
    return mergeContactDetails(remote ?? SITE_CONTACT_FIXTURE);
  },
};
