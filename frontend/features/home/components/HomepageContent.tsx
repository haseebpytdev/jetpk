import {
  FeaturedOffersSection,
  HomepageContentService,
  PublicHero,
  PublicSupportBanner,
  RoutesSection,
  WhyJetPakistanSection,
} from "@/features/public-visual";

export async function HomepageContent() {
  const content = await HomepageContentService.getHomepage();

  return (
    <>
      <PublicHero
        hero={content.hero}
        trustChips={content.trustChips}
        fallbackImage={HomepageContentService.heroFallbackImage}
      />
      <RoutesSection {...content.routes} />
      <FeaturedOffersSection {...content.featuredDeals} />
      <WhyJetPakistanSection {...content.whyBook} />
      <PublicSupportBanner support={content.supportCta} />
    </>
  );
}
