"use client";

import dynamic from "next/dynamic";
import { PageContainer } from "@/components/layout/PageContainer";
import { ImageSlot } from "@/components/ui/ImageSlot";
import { cn } from "@/lib/cn";
import type { HomepageContent, HomepageHeroContent, HomepageTrustChip } from "../types/homepage";
import { BenefitStrip } from "../components/BenefitStrip";

const SearchModule = dynamic(
  () => import("@/features/search").then((mod) => mod.SearchModule),
  {
    ssr: false,
    loading: () => (
      <div
        className="min-h-[12rem] rounded-jp-card border border-white/20 bg-jp-surface/90 p-4 shadow-jp-card"
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
  /**
   * CMS published empty strings are authoritative when source is `cms`.
   * Fixture / empty (no CMS) may use marketing fallbacks for copy.
   */
  contentSource: HomepageContent["source"];
};

const FIXTURE_COPY = {
  headline: "Explore the world with",
  highlight: "JetPakistan",
  subtitle:
    "Compare flights, pay in PKR, and book with a Pakistan-focused travel platform you can trust.",
} as const;

/**
 * Hero media canvas wraps copy + search shell so the image continues behind the full
 * composition. Height is a stable responsive minimum (not tied to active trip mode).
 */
export function PublicHero({ hero, trustChips, fallbackImage, contentSource }: PublicHeroProps) {
  const cmsAuthoritative = contentSource === "cms";
  const eyebrow = hero.eyebrow ?? "";
  const rawHeadline = hero.headline ?? "";
  const rawHighlight = hero.headlineHighlight ?? "";
  const rawSubtitle = hero.subtitle ?? "";

  const headline = cmsAuthoritative ? rawHeadline : rawHeadline || FIXTURE_COPY.headline;
  const highlight = cmsAuthoritative ? rawHighlight : rawHighlight || FIXTURE_COPY.highlight;
  const subtitle = cmsAuthoritative ? rawSubtitle : rawSubtitle || FIXTURE_COPY.subtitle;

  const hasTitle = headline.trim() !== "" || highlight.trim() !== "";
  const desktopSrc = hero.image?.url ?? fallbackImage;
  const mobileSrc = hero.imageMobile?.url ?? desktopSrc;
  const objectPosition =
    hero.focalPoint === "left" ? "left center" : hero.focalPoint === "right" ? "right center" : "center";

  return (
    <section className="relative overflow-x-hidden" data-testid="homepage-public-hero">
      <div
        className={cn(
          "relative isolate overflow-hidden",
          // Fixed canvas heights so trip/service mode switches do not resize/crop.
          // Sized above tallest initial shell; 47rem clears 320px pad>=16 gate.
          "h-[47rem] sm:h-[48rem] md:h-[48rem] lg:h-[50rem] xl:h-[52rem]",
        )}
        data-testid="homepage-hero-backdrop"
      >
        <div className="absolute inset-0" data-testid="homepage-hero-image" aria-hidden={!hasTitle}>
          <picture className="absolute inset-0 block h-full w-full">
            <source media="(max-width: 767px)" srcSet={mobileSrc} />
            <ImageSlot
              src={desktopSrc}
              alt={hero.image?.alt ?? hero.imageMobile?.alt ?? "JetPakistan flights"}
              width={1440}
              height={560}
              priority
              sizes="100vw"
              fillContainer
              className="!absolute !inset-0 !h-full !w-full !max-w-none !rounded-none [&_img]:!h-full [&_img]:!w-full [&_img]:!object-cover"
              objectFit="cover"
              objectPosition={objectPosition}
              fallbackLabel="JetPakistan hero"
              brandedFallback
            />
          </picture>
          <div
            className="absolute inset-0 bg-gradient-to-b from-black/40 via-black/25 to-black/55 dark:from-black/55 dark:via-black/40 dark:to-black/70"
            aria-hidden="true"
          />
        </div>

        <div className="relative z-10 flex min-h-[inherit] flex-col">
          <PageContainer className="flex flex-1 flex-col justify-end pb-6 pt-jp-3xl sm:pb-8 sm:pt-jp-4xl">
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

          {hero.searchVisible ? (
            <PageContainer className="relative z-20 overflow-x-visible pb-6 sm:pb-8 md:pb-10">
              <div data-testid="homepage-hero-search-overlap">
                <SearchModule layout="compact" />
                <BenefitStrip items={trustChips} variant="hero" className="mt-jp-md" />
              </div>
            </PageContainer>
          ) : null}
        </div>
      </div>
    </section>
  );
}
