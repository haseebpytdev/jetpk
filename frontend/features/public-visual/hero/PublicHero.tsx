"use client";

import { AnimatedFlightPath } from "@/components/motion/AnimatedFlightPath";
import { PageContainer } from "@/components/layout/PageContainer";
import { SearchModule } from "@/features/search";
import { cn } from "@/lib/cn";
import type { HomepageHeroContent, HomepageTrustChip } from "../types/homepage";
import { AssetSlot } from "../components/AssetSlot";
import { BenefitStrip } from "../components/BenefitStrip";

type PublicHeroProps = {
  hero: HomepageHeroContent;
  trustChips: HomepageTrustChip[];
};

const HERO_BAND_HEIGHT_PX = 420;
const SEARCH_OVERLAP_PX = 108;

export function PublicHero({ hero, trustChips }: PublicHeroProps) {
  const headline = hero.headline || "Explore the World with";
  const highlight = hero.headlineHighlight || "JetPakistan";
  const subtitle =
    hero.subtitle ||
    "Find the best flight deals to your dream destinations. Book with confidence and fly with ease.";

  return (
    <section className="relative bg-jp-page" data-testid="public-hero">
      <div
        data-testid="hero-image-band"
        className="relative overflow-hidden"
        style={{ height: HERO_BAND_HEIGHT_PX }}
      >
        <div className="absolute inset-0">
          <AssetSlot
            src={hero.image?.url ?? null}
            alt={hero.image?.alt ?? "JetPakistan flights"}
            width={1122}
            height={HERO_BAND_HEIGHT_PX}
            variant="hero-neutral"
            className="!rounded-none"
            priority
            sizes="1122px"
          />
          <div className="public-hero-overlay absolute inset-0" aria-hidden="true" />
        </div>

        <PageContainer className="relative z-10 flex h-full flex-col justify-end pb-8">
          <div data-testid="hero-text-block" className="max-w-[34rem] text-white">
            {hero.eyebrow ? (
              <p className="text-jp-sm font-semibold uppercase tracking-[0.18em] text-white/85">{hero.eyebrow}</p>
            ) : null}
            <h1 className="mt-2 font-display text-[clamp(2rem,3.6vw,3rem)] font-bold leading-[1.08] text-white">
              {headline}{" "}
              <span className="text-jp-primary-soft">{highlight}</span>
            </h1>
            <p className="mt-3 max-w-xl text-jp-body leading-relaxed text-white/90">{subtitle}</p>
          </div>
        </PageContainer>
      </div>

      {hero.searchVisible ? (
        <div
          data-testid="search-dock"
          className="relative z-20"
          style={{ marginTop: -SEARCH_OVERLAP_PX }}
        >
          <PageContainer>
            <SearchModule layout="blueprint" variant="blueprint" />
            <BenefitStrip items={trustChips} className="mt-[19px]" />
          </PageContainer>
        </div>
      ) : null}
    </section>
  );
}

export function HomepageFlightPathAccent({ className }: { className?: string }) {
  return (
    <AnimatedFlightPath
      className={cn("mx-auto w-full max-w-[960px] opacity-80 [&_svg]:h-6", className)}
      variant="compact"
    />
  );
}
