"use client";

import dynamic from "next/dynamic";
import { PageContainer } from "@/components/layout/PageContainer";
import { ImageSlot } from "@/components/ui/ImageSlot";
import { cn } from "@/lib/cn";
import type { HomepageHeroContent, HomepageTrustChip } from "../types/homepage";
import { BenefitStrip } from "../components/BenefitStrip";

const SearchModule = dynamic(
  () => import("@/features/search").then((mod) => mod.SearchModule),
  {
    ssr: false,
    loading: () => (
      <div
        className="min-h-[12rem] rounded-jp-card border border-white/20 bg-white/90 p-4 shadow-jp-card"
        data-testid="homepage-search-placeholder"
        aria-busy="true"
      />
    ),
  },
);

type PublicHeroProps = {
  hero: HomepageHeroContent;
  trustChips: HomepageTrustChip[];
  fallbackImage: string;
};

export function PublicHero({ hero, trustChips, fallbackImage }: PublicHeroProps) {
  const headline = hero.headline || "Explore the world with";
  const highlight = hero.headlineHighlight || "JetPakistan";
  const subtitle =
    hero.subtitle ||
    "Compare flights, pay in PKR, and book with a Pakistan-focused travel platform you can trust.";
  const desktopSrc = hero.image?.url ?? fallbackImage;
  const mobileSrc = hero.imageMobile?.url ?? desktopSrc;
  const objectPosition =
    hero.focalPoint === "left" ? "left center" : hero.focalPoint === "right" ? "right center" : "center";

  return (
    <section className="relative overflow-hidden border-b border-jp-border bg-jp-page" data-testid="homepage-public-hero">
      <div className="relative min-h-[clamp(20rem,42vh,30rem)]">
        <div className="absolute inset-0" data-testid="homepage-hero-image">
          <picture>
            <source media="(max-width: 767px)" srcSet={mobileSrc} />
            <ImageSlot
              src={desktopSrc}
              alt={hero.image?.alt ?? hero.imageMobile?.alt ?? "JetPakistan flights"}
              width={1440}
              height={560}
              priority
              sizes="100vw"
              className="!max-w-none !rounded-none h-full w-full"
              objectFit="cover"
              objectPosition={objectPosition}
              fallbackLabel="JetPakistan hero"
              brandedFallback
            />
          </picture>
          <div
            className="absolute inset-0 bg-gradient-to-b from-black/35 via-black/20 to-jp-page/95 dark:from-black/55 dark:via-black/35 dark:to-jp-page"
            aria-hidden="true"
          />
        </div>

        <PageContainer className="relative z-10 flex min-h-[clamp(20rem,42vh,30rem)] flex-col justify-end pb-0 pt-jp-3xl">
          <div className="max-w-3xl pb-jp-lg text-white">
            {hero.eyebrow ? (
              <p className="text-jp-sm font-semibold uppercase tracking-[0.18em] text-white/85">{hero.eyebrow}</p>
            ) : null}
            <h1 className="mt-3 font-display text-jp-h1 font-bold leading-tight text-white">
              {headline}{" "}
              <span className="text-jp-primary-soft">{highlight}</span>
            </h1>
            <p className="mt-4 max-w-2xl text-jp-body leading-relaxed text-white/90">{subtitle}</p>
          </div>

          {hero.searchVisible ? (
            <div className="relative z-20 -mb-6 sm:-mb-8" data-testid="homepage-hero-search-overlap">
              <SearchModule layout="compact" />
              <BenefitStrip items={trustChips} variant="hero" className="mt-jp-md" />
            </div>
          ) : null}
        </PageContainer>
      </div>
      {/* Compact overlap reserve only — matches -mb-* above; no orphan decorative path. */}
      <div
        className={cn(hero.searchVisible ? "h-6 sm:h-8" : "h-0")}
        aria-hidden="true"
        data-testid="homepage-hero-overlap-spacer"
      />
    </section>
  );
}
