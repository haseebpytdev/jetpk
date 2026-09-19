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

/**
 * CMS-published empty strings are authoritative — do not substitute marketing fallbacks.
 * Fixture/missing CMS source may still supply non-empty strings via the content service.
 */
export function PublicHero({ hero, trustChips, fallbackImage }: PublicHeroProps) {
  const eyebrow = hero.eyebrow ?? "";
  const headline = hero.headline ?? "";
  const highlight = hero.headlineHighlight ?? "";
  const subtitle = hero.subtitle ?? "";
  const hasTitle = headline.trim() !== "" || highlight.trim() !== "";
  const desktopSrc = hero.image?.url ?? fallbackImage;
  const mobileSrc = hero.imageMobile?.url ?? desktopSrc;
  const objectPosition =
    hero.focalPoint === "left" ? "left center" : hero.focalPoint === "right" ? "right center" : "center";

  return (
    <section className="relative overflow-x-hidden bg-jp-page" data-testid="homepage-public-hero">
      {/* Stable viewport-tied backdrop — height must not track search-mode form height. */}
      <div
        className="relative h-[clamp(20rem,42vh,30rem)] overflow-hidden"
        data-testid="homepage-hero-backdrop"
      >
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
            className="absolute inset-0 bg-gradient-to-b from-black/35 via-black/20 to-black/50 dark:from-black/55 dark:via-black/35 dark:to-black/65"
            aria-hidden="true"
          />
        </div>

        <PageContainer className="relative z-10 flex h-full flex-col justify-end pb-24 pt-jp-3xl sm:pb-28">
          <div className="max-w-3xl min-w-0 text-white">
            {eyebrow.trim() !== "" ? (
              <p className="text-jp-sm font-semibold uppercase tracking-[0.18em] text-white/85">{eyebrow}</p>
            ) : null}
            {hasTitle ? (
              <h1
                className={cn(
                  "break-words font-display text-jp-h1 font-bold leading-[1.15] text-white",
                  eyebrow.trim() !== "" && "mt-3",
                )}
              >
                {headline}
                {headline.trim() !== "" && highlight.trim() !== "" ? " " : null}
                {highlight.trim() !== "" ? (
                  <span className="text-jp-primary-soft">{highlight}</span>
                ) : null}
              </h1>
            ) : null}
            {subtitle.trim() !== "" ? (
              <p className="mt-4 max-w-2xl text-jp-body leading-relaxed text-white/90">{subtitle}</p>
            ) : null}
          </div>
        </PageContainer>
      </div>

      {hero.searchVisible ? (
        <PageContainer className="relative z-20 -mt-16 pb-jp-lg sm:-mt-20">
          <div data-testid="homepage-hero-search-overlap">
            <SearchModule layout="compact" />
            <BenefitStrip items={trustChips} variant="hero" className="mt-jp-md" />
          </div>
        </PageContainer>
      ) : null}
    </section>
  );
}
