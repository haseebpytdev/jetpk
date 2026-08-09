"use client";

import { PageContainer } from "@/components/layout/PageContainer";
import { SectionContainer } from "@/components/layout/SectionContainer";
import { Badge } from "@/components/ui/Badge";
import { SecondaryButton } from "@/components/ui/SecondaryButton";
import { ImageSlot } from "@/components/ui/ImageSlot";
import { ScrollReveal } from "@/features/motion";
import { resolveDestinationMedia } from "@/lib/homepage-media";
import Link from "next/link";
import { useRef } from "react";
import type { HomepageDestinationCard, HomepageSectionHeader } from "../types/homepage";
import { PublicSectionHeader } from "../components/PublicSectionHeader";

type DestinationsSectionProps = HomepageSectionHeader & {
  items: HomepageDestinationCard[];
};

export function DestinationsSection({
  enabled,
  eyebrow,
  title,
  subtitle,
  ctaText,
  ctaUrl,
  items,
}: DestinationsSectionProps) {
  const scrollerRef = useRef<HTMLDivElement>(null);

  if (!enabled || items.length === 0) return null;

  const scrollBy = (direction: -1 | 1) => {
    const node = scrollerRef.current;
    if (!node) return;
    node.scrollBy({ left: direction * Math.max(node.clientWidth * 0.8, 280), behavior: "smooth" });
  };

  return (
    <ScrollReveal as="section" data-testid="homepage-destinations-section">
      <SectionContainer>
        <PageContainer>
          <PublicSectionHeader
            eyebrow={eyebrow}
            title={title || "Destinations on the Rise"}
            subtitle={subtitle}
            ctaText={ctaText}
            ctaUrl={ctaUrl}
          />

          <div className="mt-jp-lg flex items-center gap-2">
            <SecondaryButton
              type="button"
              aria-label="Scroll destinations left"
              className="hidden sm:inline-flex"
              onClick={() => scrollBy(-1)}
            >
              ←
            </SecondaryButton>
            <div
              ref={scrollerRef}
              className="flex flex-1 snap-x snap-mandatory gap-4 overflow-x-auto pb-2 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
              role="region"
              aria-label="Destination cards"
            >
              {items.map((destination, index) => {
                const media = resolveDestinationMedia(destination, index);
                const cardBody = (
                  <>
                    <div className="relative aspect-[4/3] bg-jp-surface-muted">
                      <ImageSlot
                        src={media.image}
                        alt={media.imageAlt}
                        width={272}
                        height={204}
                        sizes="(max-width: 768px) 85vw, 272px"
                        className="!max-w-none h-full w-full !rounded-none"
                        fallbackLabel={destination.title}
                      />
                    </div>
                    <div className="p-jp-md">
                      {destination.code ? <Badge variant="new">{destination.code}</Badge> : null}
                      <h3 className="mt-2 font-display text-jp-md font-semibold text-jp-text">{destination.title}</h3>
                      {destination.country ? (
                        <p className="text-jp-sm text-jp-muted">{destination.country}</p>
                      ) : destination.text ? (
                        <p className="text-jp-sm text-jp-muted">{destination.text}</p>
                      ) : null}
                      {destination.priceLabel ? (
                        <p className="mt-2 text-jp-sm font-semibold text-jp-primary">{destination.priceLabel}</p>
                      ) : null}
                    </div>
                  </>
                );

                return (
                  <article
                    key={destination.id}
                    className="w-[min(85vw,17rem)] shrink-0 snap-start overflow-hidden rounded-jp-card border border-jp-border bg-jp-surface shadow-jp-card"
                  >
                    {destination.href ? (
                      <Link href={destination.href} className="block focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-jp-primary">
                        {cardBody}
                      </Link>
                    ) : (
                      cardBody
                    )}
                  </article>
                );
              })}
            </div>
            <SecondaryButton
              type="button"
              aria-label="Scroll destinations right"
              className="hidden sm:inline-flex"
              onClick={() => scrollBy(1)}
            >
              →
            </SecondaryButton>
          </div>
        </PageContainer>
      </SectionContainer>
    </ScrollReveal>
  );
}
