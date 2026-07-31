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
        <div className="flex flex-col" data-testid="homepage-discovery-bridge">
          <ScrollToDiscover />
          <HomepageFlightPathAccent />
        </div>
        <RoutesSection {...content.routes} sectionClassName="pt-jp-sm pb-jp-3xl sm:pt-jp-md lg:pt-0 lg:pb-jp-3xl" />
        <FeaturedOffersSection {...content.featuredDeals} />
        <WhyJetPakistanSection {...content.whyBook} />
        <PublicSupportBanner support={content.supportCta} />
      </div>
    </>
  );
}
