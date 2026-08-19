"use client";

import { PageContainer } from "@/components/layout/PageContainer";
import { SectionContainer } from "@/components/layout/SectionContainer";
import { ImageSlot } from "@/components/ui/ImageSlot";
import { ScrollReveal } from "@/features/motion";
import { resolveOfferMedia } from "@/lib/homepage-media";
import type { HomepageFeaturedDeal, HomepageSectionHeader } from "../types/homepage";
import { PublicSectionHeader } from "../components/PublicSectionHeader";
import { FullCardRail, fullCardArticleClass } from "../components/FullCardRail";

type FeaturedOffersSectionProps = HomepageSectionHeader & {
  items: HomepageFeaturedDeal[];
};

export function FeaturedOffersSection({
  enabled,
  eyebrow,
  title,
  subtitle,
  ctaText,
  ctaUrl,
  items,
}: FeaturedOffersSectionProps) {
  if (!enabled || items.length === 0) return null;

  return (
    <ScrollReveal as="section">
      <SectionContainer className="!py-8 sm:!py-10 bg-jp-surface-muted/40">
        <PageContainer>
          <PublicSectionHeader
            eyebrow={eyebrow}
            title={title || "Featured Offers"}
            subtitle={subtitle}
            ctaText={ctaText}
            ctaUrl={ctaUrl}
          />

          <FullCardRail
            itemCount={items.length}
            ariaLabel="Live fare cards"
            prevLabel="Scroll live fares left"
            nextLabel="Scroll live fares right"
          >
            {items.map((offer, index) => {
              const media = resolveOfferMedia(offer, index);

              return (
                <article key={offer.id} className={fullCardArticleClass} data-testid="featured-offer-card">
                  <div className="relative aspect-[16/9] bg-jp-surface-muted">
                    <ImageSlot
                      src={media.image}
                      alt={media.imageAlt}
                      width={480}
                      height={270}
                      sizes="(max-width: 640px) 100vw, (max-width: 900px) 50vw, (max-width: 1000px) 33vw, 25vw"
                      className="!max-w-none h-full w-full !rounded-none"
                      fallbackLabel={offer.from}
                      brandedFallback
                    />
                  </div>
                  <div className="flex flex-1 flex-col p-jp-md">
                    {offer.airline ? (
                      <p className="text-jp-xs uppercase tracking-wide text-jp-muted">{offer.airline}</p>
                    ) : null}
                    <h3 className="mt-2 font-sans text-jp-md font-semibold text-jp-text">
                      {offer.from}
                      {offer.to ? (
                        <>
                          <span className="sr-only"> — </span>
                          <span className="mt-1 block text-jp-sm font-medium text-jp-muted">{offer.to}</span>
                        </>
                      ) : null}
                    </h3>
                    {offer.priceLabel ? (
                      <p className="mt-3 text-jp-sm font-semibold text-jp-primary">{offer.priceLabel}</p>
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
