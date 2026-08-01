import { PageContainer } from "@/components/layout/PageContainer";
import { SectionContainer } from "@/components/layout/SectionContainer";
import { cn } from "@/lib/cn";
import type { HomepageInspirationCard, HomepageSectionHeader } from "../types/homepage";
import { AssetSlot } from "../components/AssetSlot";
import { PublicSectionHeader } from "../components/PublicSectionHeader";

type TravelInspirationSectionProps = HomepageSectionHeader & {
  items: HomepageInspirationCard[];
  compact?: boolean;
};

export function TravelInspirationSection({
  enabled,
  eyebrow,
  title,
  subtitle,
  ctaText,
  ctaUrl,
  items,
  compact = false,
}: TravelInspirationSectionProps) {
  if (!enabled || items.length === 0) return null;

  const displayItems = items.slice(0, 4);

  return (
    <SectionContainer className={compact ? "py-1" : "py-4"}>
      <PageContainer>
        <PublicSectionHeader
          eyebrow={eyebrow}
          title={title || "Travel Inspiration"}
          subtitle={subtitle || "Stories, guides, and tips for your next adventure"}
          ctaText={ctaText || "View all articles"}
          ctaUrl={ctaUrl || "/"}
          compact={compact}
        />

        <div className={cn("grid sm:grid-cols-2 lg:grid-cols-4", compact ? "mt-1 gap-2" : "mt-4 gap-3")}>
          {displayItems.map((article) => (
            <article
              key={article.id}
              className="overflow-hidden rounded-xl border border-jp-border bg-jp-surface shadow-jp-sm"
            >
                <div className={cn("relative overflow-hidden", compact ? "h-10" : "aspect-[16/10]")}>
                <AssetSlot
                  src={article.image}
                  alt={article.imageAlt ?? article.title}
                  width={220}
                  height={138}
                  variant="card-neutral"
                />
              </div>
              <div className={compact ? "p-1.5" : "p-3"}>
                {article.category ? (
                  <p className={cn("font-semibold uppercase tracking-wider text-jp-primary", compact ? "text-[9px]" : "text-[10px]")}>
                    {article.category}
                  </p>
                ) : null}
                <h3 className={cn("font-display font-semibold leading-snug text-jp-text", compact ? "mt-0.5 text-[10px]" : "mt-1 text-jp-sm")}>
                  {article.title}
                </h3>
                {!compact && (article.publishedAt || article.readingTime) ? (
                  <p className="mt-2 text-[10px] text-jp-muted">
                    {[article.publishedAt, article.readingTime].filter(Boolean).join(" · ")}
                  </p>
                ) : null}
              </div>
            </article>
          ))}
        </div>
      </PageContainer>
    </SectionContainer>
  );
}
