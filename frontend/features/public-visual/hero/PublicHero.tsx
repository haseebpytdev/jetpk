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
   * `cms` and `empty` preserve blanks (no JetPakistan backfill).
   * Only explicit `fixture` source may use marketing fallbacks.
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
 * Content-driven hero: section height follows copy + search + trust strip.
 * Absolute media fills that box so the image covers the composition without a
 * fixed 47–52rem dead zone below the search shell.
 * Search zone keeps a stable min-height so mode switches do not crop-jump.
 */
export function PublicHero({ hero, trustChips, fallbackImage, contentSource }: PublicHeroProps) {
  const useFixtureCopy = contentSource === "fixture";
  const eyebrow = hero.eyebrow ?? "";
  const rawHeadline = hero.headline ?? "";
  const rawHighlight = hero.headlineHighlight ?? "";
  const rawSubtitle = hero.subtitle ?? "";

  // Explicit CMS / empty blanks stay blank — never substitute JetPakistan.
  const headline = useFixtureCopy ? rawHeadline || FIXTURE_COPY.headline : rawHeadline;
  const highlight = useFixtureCopy ? rawHighlight || FIXTURE_COPY.highlight : rawHighlight;
  const subtitle = useFixtureCopy ? rawSubtitle || FIXTURE_COPY.subtitle : rawSubtitle;

  const hasTitle = headline.trim() !== "" || highlight.trim() !== "";
  const desktopSrc = hero.image?.url ?? fallbackImage;
  const mobileSrc = hero.imageMobile?.url ?? desktopSrc;
  const objectPosition =
    hero.focalPoint === "left" ? "left center" : hero.focalPoint === "right" ? "right center" : "center";

  return (
    <section
      className="relative isolate overflow-x-hidden"
      data-testid="homepage-public-hero"
    >
      <div className="relative isolate overflow-hidden" data-testid="homepage-hero-backdrop">
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

        <div className="relative z-10 flex flex-col">
          <PageContainer className="pb-4 pt-jp-3xl sm:pb-5 sm:pt-jp-4xl">
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
                  data-testid="homepage-hero-h1"
                >
                  {headline.trim() !== "" ? (
                    <span className="block" data-testid="homepage-hero-headline">
                      {headline}
                    </span>
                  ) : null}
                  {highlight.trim() !== "" ? (
                    <span
                      className={cn("block text-jp-primary-soft", headline.trim() !== "" && "mt-1")}
                      data-testid="homepage-hero-highlight"
                    >
                      {highlight}
                    </span>
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
              {/* Stable reservation for tallest initial search shell (multi-city / group). */}
              <div
                data-testid="homepage-hero-search-overlap"
                className="min-h-[22rem] sm:min-h-[20rem] md:min-h-[18rem] lg:min-h-[16rem]"
              >
                <div data-testid="homepage-search-shell">
                  <SearchModule layout="compact" />
                </div>
                <BenefitStrip items={trustChips} variant="hero" className="mt-jp-md" />
              </div>
            </PageContainer>
          ) : null}
        </div>
      </div>
    </section>
  );
}
