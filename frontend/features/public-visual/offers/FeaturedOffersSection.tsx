import { PageContainer } from "@/components/layout/PageContainer";
import { SectionContainer } from "@/components/layout/SectionContainer";
import { cn } from "@/lib/cn";
import type { HomepageOfferCard, HomepageSectionHeader } from "../types/homepage";
import { PublicSectionHeader } from "../components/PublicSectionHeader";
import { OfferCard } from "./OfferCard";

type FeaturedOffersSectionProps = HomepageSectionHeader & {
  items: HomepageOfferCard[];
  compact?: boolean;
};

export function FeaturedOffersSection({
  enabled,
  eyebrow,
  title,
  subtitle,
  ctaText,
  ctaUrl,
  items,
  compact = false,
}: FeaturedOffersSectionProps) {
  if (!enabled || items.length === 0) return null;

  const displayItems = items.slice(0, 3);

  return (
    <SectionContainer className={compact ? "bg-jp-surface-muted/30 py-1" : "bg-jp-surface-muted/30 py-4"}>
      <PageContainer>
        <PublicSectionHeader
          eyebrow={eyebrow}
          title={title || "Featured Offers"}
          subtitle={subtitle || "Limited time deals on top destinations"}
          ctaText={ctaText || "View all offers"}
          ctaUrl={ctaUrl || "/"}
          compact={compact}
        />

        <div className={cn("grid gap-3 lg:grid-cols-3", compact ? "mt-1 gap-2" : "mt-4")}>
          {displayItems.map((offer) => (
            <OfferCard key={offer.id} offer={offer} compact={compact} />
          ))}
        </div>
      </PageContainer>
    </SectionContainer>
  );
}
