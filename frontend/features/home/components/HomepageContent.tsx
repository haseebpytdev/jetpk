import { Suspense } from "react";
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
 * Soft-nav: stream hero/search shell first; below-fold CMS sections follow in Suspense.
 * SEO content remains SSR inside each async boundary (not client-only).
 */
export function HomepageContent() {
  return (
    <>
      <Suspense
        fallback={
          <div
            className="relative min-h-[22rem] bg-jp-page"
            data-testid="homepage-hero-suspense-fallback"
            aria-busy="true"
          />
        }
      >
        <HomepageHeroBlock />
      </Suspense>
      <Suspense fallback={null}>
        <HomepageBelowFoldBlock />
      </Suspense>
    </>
  );
}

async function HomepageHeroBlock() {
  const content = await HomepageContentService.getHomepage();
  return (
    <PublicHero
      hero={content.hero}
      trustChips={content.trustChips}
      fallbackImage={HomepageContentService.heroFallbackImage}
      contentSource={content.source}
    />
  );
}

async function HomepageBelowFoldBlock() {
  const content = await HomepageContentService.getHomepage();
  return (
    <>
      <RoutesSection {...content.routes} />
      <DestinationsSection {...content.destinations} />
      <FeaturedOffersSection {...content.featuredDeals} />
      <WhyJetPakistanSection {...content.whyBook} />
      <PublicSupportBanner support={content.supportCta} />
    </>
  );
}
