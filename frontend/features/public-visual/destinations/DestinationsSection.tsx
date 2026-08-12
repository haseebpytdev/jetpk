"use client";

import { PageContainer } from "@/components/layout/PageContainer";
import { SectionContainer } from "@/components/layout/SectionContainer";
import { Badge } from "@/components/ui/Badge";
import { ImageSlot } from "@/components/ui/ImageSlot";
import { ScrollReveal } from "@/features/motion";
import { resolveDestinationMedia } from "@/lib/homepage-media";
import Link from "next/link";
import type { HomepageDestinationCard, HomepageSectionHeader } from "../types/homepage";
import { PublicSectionHeader } from "../components/PublicSectionHeader";
import { FullCardRail, fullCardArticleClass } from "../components/FullCardRail";

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
  if (!enabled || items.length === 0) return null;

  return (
    <ScrollReveal as="section" data-testid="homepage-destinations-section">
      <SectionContainer className="!py-8 sm:!py-10">
        <PageContainer>
          <PublicSectionHeader
            eyebrow={eyebrow}
            title={title || "Destinations on the rise."}
            subtitle={subtitle}
            ctaText={ctaText}
            ctaUrl={ctaUrl}
          />

          <FullCardRail
            itemCount={items.length}
            ariaLabel="Destination cards"
            prevLabel="Scroll destinations left"
            nextLabel="Scroll destinations right"
          >
            {items.map((destination, index) => {
              const media = resolveDestinationMedia(destination, index);
              const cardBody = (
                <>
                  <div className="relative aspect-[4/3] bg-jp-surface-muted">
                    <ImageSlot
                      src={media.image}
                      alt={media.imageAlt}
                      width={320}
                      height={240}
                      sizes="(max-width: 640px) 100vw, (max-width: 900px) 50vw, (max-width: 1180px) 33vw, 25vw"
                      className="!max-w-none h-full w-full !rounded-none"
                      fallbackLabel={destination.title}
                      brandedFallback
                    />
                  </div>
                  <div className="p-jp-md">
                    {destination.code ? <Badge variant="new">{destination.code}</Badge> : null}
                    <h3 className="mt-2 font-sans text-jp-md font-semibold text-jp-text">{destination.title}</h3>
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
                <article key={destination.id} className={fullCardArticleClass} data-testid="destination-card">
                  {destination.href ? (
                    <Link
                      href={destination.href}
                      className="block focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-jp-primary"
                    >
                      {cardBody}
                    </Link>
                  ) : (
                    cardBody
                  )}
                </article>
              );
            })}
          </FullCardRail>
        </PageContainer>
      </SectionContainer>
    </ScrollReveal>
  );
}
