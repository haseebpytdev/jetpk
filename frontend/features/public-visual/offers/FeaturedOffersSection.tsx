"use client";

import { PageContainer } from "@/components/layout/PageContainer";
import { SectionContainer } from "@/components/layout/SectionContainer";
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
    <SectionContainer className="bg-jp-surface-muted/40">
      <PageContainer>
        <PublicSectionHeader
          eyebrow={eyebrow}
          title={title || "Featured Offers"}
          subtitle={subtitle}
          ctaText={ctaText}
          ctaUrl={ctaUrl}
        />

        <div className="mt-jp-lg grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          {items.map((offer) => (
            <article
              key={offer.id}
              className="flex min-h-[12rem] flex-col justify-between rounded-jp-card border border-jp-border bg-gradient-to-br from-jp-primary to-jp-primary-hover p-jp-lg text-white shadow-jp-card"
            >
              <div>
                {offer.airline ? <p className="text-jp-xs uppercase tracking-wide text-white/80">{offer.airline}</p> : null}
                <h3 className="mt-2 font-display text-jp-h3 font-bold">
                  {offer.from}
                  {offer.to ? ` → ${offer.to}` : ""}
                </h3>
                {offer.duration ? <p className="mt-2 text-jp-sm text-white/85">{offer.duration}</p> : null}
              </div>
              {offer.priceLabel ? <p className="mt-4 text-jp-md font-semibold">{offer.priceLabel}</p> : null}
            </article>
          ))}
        </div>
      </PageContainer>
    </SectionContainer>
  );
}
