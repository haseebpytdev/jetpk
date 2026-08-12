"use client";

import { PageContainer } from "@/components/layout/PageContainer";
import { SectionContainer } from "@/components/layout/SectionContainer";
import { ImageSlot } from "@/components/ui/ImageSlot";
import { ScrollReveal } from "@/features/motion";
import { resolveOfferMedia } from "@/lib/homepage-media";
import type { HomepageFeaturedDeal, HomepageSectionHeader } from "../types/homepage";
import { PublicSectionHeader } from "../components/PublicSectionHeader";

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

          <div className="mt-jp-md grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            {items.map((offer, index) => {
              const media = resolveOfferMedia(offer, index);

              return (
              <ScrollReveal key={offer.id} as="article" staggerIndex={index + 1}>
                <article className="flex min-h-[15rem] flex-col overflow-hidden rounded-jp-card border border-jp-border bg-jp-surface shadow-jp-card">
                  <div className="relative aspect-[16/9] bg-jp-surface-muted">
                    <ImageSlot
                      src={media.image}
                      alt={media.imageAlt}
                      width={480}
                      height={270}
                      sizes="(max-width: 768px) 100vw, 33vw"
                      className="!max-w-none h-full w-full !rounded-none"
                      fallbackLabel={offer.from}
                      brandedFallback
                    />
                  </div>
                  <div className="flex flex-1 flex-col p-jp-lg">
                    {offer.airline ? (
                      <p className="text-jp-xs uppercase tracking-wide text-jp-muted">{offer.airline}</p>
                    ) : null}
                    <h3 className="mt-2 font-sans text-jp-h3 font-bold text-jp-text">
                      {offer.from}
                      {offer.to ? (
                        <>
                          <span className="sr-only"> — </span>
                          <span className="mt-1 block text-jp-md font-medium text-jp-muted">{offer.to}</span>
                        </>
                      ) : null}
                    </h3>
                    {offer.priceLabel ? (
                      <p className="mt-4 text-jp-sm font-semibold text-jp-primary">{offer.priceLabel}</p>
                    ) : null}
                  </div>
                </article>
              </ScrollReveal>
              );
            })}
          </div>
        </PageContainer>
      </SectionContainer>
    </ScrollReveal>
  );
}
