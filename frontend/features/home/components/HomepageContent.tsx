import {
  DestinationsSection,
  FeaturedOffersSection,
  HomepageContentService,
  PublicHero,
  PublicSupportBanner,
  RoutesSection,
  WhyJetPakistanSection,
} from "@/features/public-visual";

/**
 * Soft-nav critical path: single-block homepage render (8793cc9f profile).
 * Do not split hero/below-fold behind Suspense — that regressed outbound home_* routes.
 */
export async function HomepageContent() {
  const content = await HomepageContentService.getHomepage();

  return (
    <>
      <PublicHero
        hero={content.hero}
        trustChips={content.trustChips}
        fallbackImage={HomepageContentService.heroFallbackImage}
        contentSource={content.source}
      />
      <RoutesSection {...content.routes} />
      <DestinationsSection {...content.destinations} />
      <FeaturedOffersSection {...content.featuredDeals} />
      <WhyJetPakistanSection {...content.whyBook} />
      <PublicSupportBanner support={content.supportCta} />
    </>
  );
}
