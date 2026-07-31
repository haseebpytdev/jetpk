"use client";

import { AnimatedFlightPath } from "@/components/motion/AnimatedFlightPath";
import { PageContainer } from "@/components/layout/PageContainer";
import { SectionCurve } from "@/components/layout/SectionCurve";
import { ImageSlot } from "@/components/ui/ImageSlot";
import { SearchModule } from "@/features/search";
import { cn } from "@/lib/cn";
import type { HomepageHeroContent, HomepageTrustChip } from "../types/homepage";
import { BenefitStrip } from "../components/BenefitStrip";

type PublicHeroProps = {
  hero: HomepageHeroContent;
  trustChips: HomepageTrustChip[];
  fallbackImage: string;
};

export function PublicHero({ hero, trustChips, fallbackImage }: PublicHeroProps) {
  const headline = hero.headline || "Explore the World with";
  const highlight = hero.headlineHighlight || "JetPakistan";
  const subtitle =
    hero.subtitle ||
    "Find the best flight deals to your dream destinations. Book with confidence and fly with ease.";

  return (
    <section className="relative overflow-hidden border-b border-jp-border bg-jp-page">
      <div className="relative min-h-[28rem] lg:min-h-[32rem]">
        <div className="absolute inset-0">
          <ImageSlot
            src={hero.image?.url ?? fallbackImage}
            alt={hero.image?.alt ?? "JetPakistan flights"}
            width={1440}
            height={640}
            priority
            sizes="100vw"
            className="!max-w-none !rounded-none h-full w-full"
            objectFit="cover"
            fallbackLabel="JetPakistan hero"
          />
          <div
            className="public-hero-overlay absolute inset-0"
            aria-hidden="true"
          />
        </div>

        <PageContainer className="relative z-10 flex min-h-[28rem] flex-col justify-end pb-0 pt-jp-3xl lg:min-h-[32rem]">
          <div className="max-w-2xl pb-jp-lg text-white">
            {hero.eyebrow ? (
              <p className="text-jp-sm font-semibold uppercase tracking-[0.18em] text-white/85">{hero.eyebrow}</p>
            ) : null}
            <h1 className="mt-3 font-display text-jp-h1 font-bold leading-[1.1] text-white">
              {headline}{" "}
              <span className="text-jp-primary-soft">{highlight}</span>
            </h1>
            <p className="mt-4 max-w-xl text-jp-body leading-relaxed text-white/90">{subtitle}</p>
          </div>

          {hero.searchVisible ? (
            <div className="relative z-20 -mb-14 sm:-mb-16 lg:-mb-[4.5rem]">
              <SearchModule layout="compact" variant="blueprint" />
              <BenefitStrip items={trustChips} className="mt-jp-md" />
              <AnimatedFlightPath className="mt-jp-md max-w-lg opacity-90" />
            </div>
          ) : null}
        </PageContainer>
      </div>
      <SectionCurve className="relative z-10 -mt-1 text-jp-page" />
      <div className={cn(hero.searchVisible ? "h-16 sm:h-20 lg:h-24" : "h-0")} aria-hidden="true" />
    </section>
  );
}
