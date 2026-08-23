import {
  DestinationsSection,
  FeaturedOffersSection,
  FeatureBoardSection,
  HomepageContentService,
  PublicHero,
  PublicSupportBanner,
  RoutesSection,
  WhyJetPakistanSection,
} from "@/features/public-visual";
import { cmsPreviewRequestHeaders } from "@/features/public-content/utils/cms-preview";

export async function HomepageContent({ preview = false }: { preview?: boolean }) {
  const content = await HomepageContentService.getHomepage({
    preview,
    headers: await cmsPreviewRequestHeaders(preview),
  });

  return (
    <>
      <PublicHero
        hero={content.hero}
        trustChips={content.trustChips}
        fallbackImage={HomepageContentService.heroFallbackImage}
      />
      <RoutesSection {...content.routes} />
      <DestinationsSection {...content.destinations} />
      <FeaturedOffersSection {...content.featuredDeals} />
      <WhyJetPakistanSection {...content.whyBook} />
      <FeatureBoardSection {...content.featureBoard} />
      <PublicSupportBanner support={content.supportCta} />
    </>
  );
}
