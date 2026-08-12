"use client";

import { PageContainer } from "@/components/layout/PageContainer";
import { SectionContainer } from "@/components/layout/SectionContainer";
import { ScrollReveal } from "@/features/motion";
import { ImageSlot } from "@/components/ui/ImageSlot";
import { resolveRouteMedia } from "@/lib/homepage-media";
import Link from "next/link";
import type { HomepageSectionHeader, HomepageRouteCard } from "../types/homepage";
import { PublicSectionHeader } from "../components/PublicSectionHeader";
import { FullCardRail, fullCardArticleClass } from "../components/FullCardRail";

type RoutesSectionProps = HomepageSectionHeader & {
  items: HomepageRouteCard[];
};

export function RoutesSection({ enabled, eyebrow, title, subtitle, ctaText, ctaUrl, items }: RoutesSectionProps) {
  if (!enabled || items.length === 0) return null;

  return (
    <ScrollReveal as="section">
      <SectionContainer className="!py-8 sm:!py-10">
        <PageContainer>
          <PublicSectionHeader
            eyebrow={eyebrow}
            title={title || "Where Pakistan is flying."}
            subtitle={subtitle}
            ctaText={ctaText}
            ctaUrl={ctaUrl}
          />

          <FullCardRail
            itemCount={items.length}
            ariaLabel="Trending route cards"
            prevLabel="Scroll routes left"
            nextLabel="Scroll routes right"
          >
            {items.map((route, index) => {
              const media = resolveRouteMedia(route, index);

              return (
                <article key={route.id} className={fullCardArticleClass} data-testid="route-card">
                  <div className="relative aspect-[4/3] bg-jp-surface-muted">
                    <ImageSlot
                      src={media.image}
                      alt={media.imageAlt}
                      width={320}
                      height={240}
                      sizes="(max-width: 640px) 100vw, (max-width: 900px) 50vw, (max-width: 1180px) 33vw, 25vw"
                      className="!max-w-none h-full w-full !rounded-none"
                      fallbackLabel={`${route.from} to ${route.to}`}
                      brandedFallback
                    />
                  </div>
                  <div className="p-jp-md">
                    <h3 className="font-display text-jp-md font-semibold text-jp-text">
                      {route.from} → {route.to}
                    </h3>
                    {route.priceLabel ? (
                      <p className="mt-1 text-jp-sm font-semibold text-jp-primary">{route.priceLabel}</p>
                    ) : null}
                    {route.searchUrl ? (
                      <Link
                        href={route.searchUrl}
                        className="mt-3 inline-block text-jp-sm font-semibold text-jp-primary hover:underline"
                      >
                        Search route
                      </Link>
                    ) : null}
                  </div>
                </article>
              );
            })}
          </FullCardRail>
        </PageContainer>
      </SectionContainer>
    </ScrollReveal>
  );
}
