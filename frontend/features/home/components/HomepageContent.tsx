import { preload } from "react-dom";
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

  // Server-side hero image preloads so LCP hints land in the initial document
  // (PublicHero is a client component; body <link rel=preload> alone is not reliable).
  const heroPreloads = content.hero.responsive?.preloads ?? [];
  for (const entry of heroPreloads) {
    const href = entry.href?.trim();
    if (!href) continue;
    preload(href, {
      as: "image",
      type: entry.type ?? undefined,
      media: entry.media,
    });
  }

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
