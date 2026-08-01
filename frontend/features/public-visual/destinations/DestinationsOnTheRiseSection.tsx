"use client";

import { PageContainer } from "@/components/layout/PageContainer";
import { SectionContainer } from "@/components/layout/SectionContainer";
import { SecondaryButton } from "@/components/ui/SecondaryButton";
import { cn } from "@/lib/cn";
import Link from "next/link";
import { useRef } from "react";
import type { HomepageRouteCard, HomepageSectionHeader } from "../types/homepage";
import { AssetSlot } from "../components/AssetSlot";
import { PublicSectionHeader } from "../components/PublicSectionHeader";

type DestinationsOnTheRiseSectionProps = HomepageSectionHeader & {
  items: HomepageRouteCard[];
  sectionClassName?: string;
  compact?: boolean;
};

export function DestinationsOnTheRiseSection({
  enabled,
  eyebrow,
  title,
  subtitle,
  ctaText,
  ctaUrl,
  items,
  sectionClassName,
  compact = false,
}: DestinationsOnTheRiseSectionProps) {
  const scrollerRef = useRef<HTMLDivElement>(null);

  if (!enabled || items.length === 0) return null;

  const displayItems = items.slice(0, 5);

  const scrollBy = (direction: -1 | 1) => {
    const node = scrollerRef.current;
    if (!node) return;
    node.scrollBy({ left: direction * Math.max(node.clientWidth * 0.75, 176), behavior: "smooth" });
  };

  return (
    <SectionContainer id="homepage-routes" className={sectionClassName ?? "py-jp-lg"}>
      <PageContainer data-testid="routes-section">
        <PublicSectionHeader
          eyebrow={eyebrow}
          title={title || "Destinations on the Rise"}
          subtitle={subtitle}
          ctaText={ctaText || "View all destinations"}
          ctaUrl={ctaUrl || "/"}
          compact={compact}
        />

        <div className={cn("flex items-center gap-2", compact ? "mt-1" : "mt-3")}>
          <SecondaryButton
            type="button"
            aria-label="Scroll destinations left"
            className="hidden h-8 w-8 shrink-0 rounded-full p-0 lg:inline-flex"
            onClick={() => scrollBy(-1)}
          >
            ←
          </SecondaryButton>
          <div
            ref={scrollerRef}
            className={cn(
              "grid flex-1 grid-cols-2 sm:grid-cols-3 lg:grid-cols-5",
              compact ? "gap-2 lg:gap-2" : "gap-3 lg:gap-3",
            )}
            role="region"
            aria-label="Destination route cards"
          >
            {displayItems.map((route) => (
              <article
                key={route.id}
                className="overflow-hidden rounded-xl border border-jp-border bg-jp-surface shadow-jp-sm"
              >
                <div className={cn("relative overflow-hidden", compact ? "h-10" : "aspect-[5/3]")}>
                  <AssetSlot
                    src={route.image ?? null}
                    alt={route.imageAlt ?? `${route.from} to ${route.to}`}
                    width={176}
                    height={132}
                    variant="card-neutral"
                  />
                </div>
                <div className={compact ? "p-1.5" : "p-3"}>
                  <h3 className={cn("font-display font-semibold text-jp-text", compact ? "text-[11px] leading-tight" : "text-jp-sm")}>
                    {route.from} → {route.to}
                  </h3>
                  {route.priceLabel ? (
                    <p className={cn("text-jp-muted", compact ? "mt-0.5 text-[10px]" : "mt-1 text-jp-xs")}>
                      From <span className="font-semibold text-jp-primary">{route.priceLabel}</span>
                    </p>
                  ) : null}
                  {route.airline && !compact ? (
                    <p className="mt-2 text-[10px] font-medium uppercase tracking-wide text-jp-muted">{route.airline}</p>
                  ) : null}
                  {route.searchUrl ? (
                    <Link
                      href={route.searchUrl}
                      className="sr-only"
                    >
                      Search {route.from} to {route.to}
                    </Link>
                  ) : null}
                </div>
              </article>
            ))}
          </div>
          <SecondaryButton
            type="button"
            aria-label="Scroll destinations right"
            className="hidden h-8 w-8 shrink-0 rounded-full p-0 lg:inline-flex"
            onClick={() => scrollBy(1)}
          >
            →
          </SecondaryButton>
        </div>
      </PageContainer>
    </SectionContainer>
  );
}
