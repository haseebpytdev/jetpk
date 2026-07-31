import {
  FeaturedOffersSection,
  HomepageContentService,
  HomepageFlightPathAccent,
  PublicHero,
  PublicSupportBanner,
  RoutesSection,
  ScrollToDiscover,
  WhyJetPakistanSection,
} from "@/features/public-visual";

export async function HomepageContent() {
  const content = await HomepageContentService.getHomepage();

  return (
    <>
      <div data-testid="homepage-content">
        <PublicHero
          hero={content.hero}
          trustChips={content.trustChips}
          fallbackImage={HomepageContentService.heroFallbackImage}
        />
        <ScrollToDiscover />
        <HomepageFlightPathAccent />
        <RoutesSection {...content.routes} />
        <FeaturedOffersSection {...content.featuredDeals} />
        <WhyJetPakistanSection {...content.whyBook} />
        <PublicSupportBanner support={content.supportCta} />
      </div>
    </>
  );
}
