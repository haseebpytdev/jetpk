import { allowContentFixtures } from "@/features/public-content/utils/content-policy";
import {
  DestinationsOnTheRiseSection,
  FeaturedOffersSection,
  HomepageContentService,
  HomepageFlightPathAccent,
  PublicHero,
  PublicSupportBanner,
  ScrollToDiscover,
  TravelInspirationSection,
  WhyJetPakistanSection,
} from "@/features/public-visual";

export async function HomepageContent() {
  const content = await HomepageContentService.getHomepage();
  const showInspiration =
    content.source === "fixture" &&
    allowContentFixtures() &&
    content.inspiration.enabled &&
    content.inspiration.items.length > 0;

  return (
    <div data-testid="homepage-content">
      <PublicHero hero={content.hero} trustChips={content.trustChips} />

      <div className="relative">
        <ScrollToDiscover className="mt-3" />
        <HomepageFlightPathAccent className="pointer-events-none absolute left-1/2 top-0 h-px w-px -translate-x-1/2 overflow-hidden opacity-0" />
      </div>

      <DestinationsOnTheRiseSection {...content.routes} compact sectionClassName="!py-1 !pt-0" />
      <FeaturedOffersSection {...content.promoOffers} compact />
      <WhyJetPakistanSection {...content.whyBook} compact />
      <PublicSupportBanner support={content.supportCta} compact />
      {showInspiration ? <TravelInspirationSection {...content.inspiration} compact /> : null}
    </div>
  );
}
