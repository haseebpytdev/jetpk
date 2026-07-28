import { BENEFIT_FIXTURES } from "@/features/home/fixtures/benefits";
import { DESTINATION_FIXTURES } from "@/features/home/fixtures/destinations";
import { INSPIRATION_FIXTURES, VALUE_PROPOSITION_FIXTURES } from "@/features/home/fixtures/inspiration";
import { FEATURED_OFFER_FIXTURES } from "@/features/home/fixtures/offers";

/**
 * Homepage content boundary — fixtures today, Laravel CMS/page API later.
 *
 * Future replacement:
 * ```ts
 * const response = await apiClient.get("/api/public/homepage");
 * return response.data;
 * ```
 */
export const HomepageContentService = {
  async getBenefits() {
    return BENEFIT_FIXTURES;
  },

  async getDestinations() {
    return DESTINATION_FIXTURES;
  },

  async getFeaturedOffers() {
    return FEATURED_OFFER_FIXTURES;
  },

  async getValuePropositions() {
    return VALUE_PROPOSITION_FIXTURES;
  },

  async getInspirationCards() {
    return INSPIRATION_FIXTURES;
  },
};
