"use client";

import { PageContainer } from "@/components/layout/PageContainer";
import { SectionContainer } from "@/components/layout/SectionContainer";
import { ImageSlot } from "@/components/ui/ImageSlot";
import { SecondaryButton } from "@/components/ui/SecondaryButton";
import Link from "next/link";
import { useRef } from "react";
import type { HomepageSectionHeader, HomepageRouteCard } from "../types/homepage";
import { PublicSectionHeader } from "../components/PublicSectionHeader";

type RoutesSectionProps = HomepageSectionHeader & {
  items: HomepageRouteCard[];
};

export function RoutesSection({ enabled, eyebrow, title, subtitle, ctaText, ctaUrl, items }: RoutesSectionProps) {
  const scrollerRef = useRef<HTMLDivElement>(null);

  if (!enabled || items.length === 0) return null;

  const scrollBy = (direction: -1 | 1) => {
    const node = scrollerRef.current;
    if (!node) return;
    node.scrollBy({ left: direction * Math.max(node.clientWidth * 0.8, 280), behavior: "smooth" });
  };

  return (
    <SectionContainer id="homepage-routes">
      <PageContainer data-testid="routes-section">
        <PublicSectionHeader
          eyebrow={eyebrow}
          title={title || "Destinations on the Rise"}
          subtitle={subtitle}
          ctaText={ctaText}
          ctaUrl={ctaUrl}
        />

        <div className="mt-jp-lg flex items-center gap-2">
          <SecondaryButton type="button" aria-label="Scroll routes left" className="hidden sm:inline-flex" onClick={() => scrollBy(-1)}>
            ←
          </SecondaryButton>
          <div
            ref={scrollerRef}
            className="flex flex-1 snap-x snap-mandatory gap-4 overflow-x-auto pb-2 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
            role="region"
            aria-label="Trending route cards"
          >
            {items.map((route) => (
              <article
                key={route.id}
                className="w-[min(85vw,17rem)] shrink-0 snap-start overflow-hidden rounded-jp-card border border-jp-border bg-jp-surface shadow-jp-card"
              >
                <div className="relative aspect-[4/3] bg-jp-surface-muted">
                  <ImageSlot
                    src={null}
                    alt={`${route.from} to ${route.to}`}
                    width={272}
                    height={204}
                    className="!max-w-none h-full w-full !rounded-none"
                    fallbackLabel={`${route.from} to ${route.to}`}
                  />
                </div>
                <div className="p-jp-md">
                  <h3 className="font-display text-jp-md font-semibold text-jp-text">
                    {route.from} → {route.to}
                  </h3>
                  {route.priceLabel ? <p className="mt-1 text-jp-sm font-semibold text-jp-primary">{route.priceLabel}</p> : null}
                  {route.searchUrl ? (
                    <Link href={route.searchUrl} className="mt-3 inline-block text-jp-sm font-semibold text-jp-primary hover:underline">
                      Search route
                    </Link>
                  ) : null}
                </div>
              </article>
            ))}
          </div>
          <SecondaryButton type="button" aria-label="Scroll routes right" className="hidden sm:inline-flex" onClick={() => scrollBy(1)}>
            →
          </SecondaryButton>
        </div>
      </PageContainer>
    </SectionContainer>
  );
}
