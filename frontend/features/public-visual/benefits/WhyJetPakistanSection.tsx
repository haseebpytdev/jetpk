import { PageContainer } from "@/components/layout/PageContainer";
import { SectionContainer } from "@/components/layout/SectionContainer";
import { cn } from "@/lib/cn";
import type { HomepageSectionHeader, HomepageWhyCard } from "../types/homepage";
import { PublicSectionHeader } from "../components/PublicSectionHeader";

type WhyJetPakistanSectionProps = HomepageSectionHeader & {
  cards: HomepageWhyCard[];
  compact?: boolean;
};

const ICONS = ["fare", "secure", "flexible", "baggage", "ontime"] as const;

export function WhyJetPakistanSection({
  enabled,
  eyebrow,
  title,
  subtitle,
  cards,
  compact = false,
}: WhyJetPakistanSectionProps) {
  if (!enabled || cards.length === 0) return null;

  const displayCards = cards.slice(0, 5);

  return (
    <SectionContainer className={compact ? "py-1" : "py-4"}>
      <PageContainer>
        <PublicSectionHeader eyebrow={eyebrow} title={title || "Why JetPakistan?"} subtitle={subtitle} compact={compact} />

        <div className={cn("grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5", compact ? "mt-1 gap-2" : "mt-4 gap-3")}>
          {displayCards.map((card, index) => (
            <article key={card.id} className="text-center">
              <div
                className={cn(
                  "mx-auto flex items-center justify-center rounded-full border border-jp-primary/30 text-jp-primary",
                  compact ? "h-8 w-8" : "h-12 w-12",
                )}
              >
                <WhyIcon type={(card.icon as (typeof ICONS)[number]) ?? ICONS[index % ICONS.length]} compact={compact} />
              </div>
              <h3 className={cn("font-display font-semibold text-jp-text", compact ? "mt-1 text-[10px] leading-tight" : "mt-2 text-jp-xs")}>
                {card.title}
              </h3>
              {card.text && !compact ? (
                <p className="mt-1 text-[10px] leading-snug text-jp-muted">{card.text}</p>
              ) : null}
            </article>
          ))}
        </div>
      </PageContainer>
    </SectionContainer>
  );
}

function WhyIcon({ type, compact = false }: { type: (typeof ICONS)[number]; compact?: boolean }) {
  const className = compact ? "h-4 w-4" : "h-5 w-5";
  if (type === "fare") {
    return (
      <svg viewBox="0 0 24 24" className={className} aria-hidden="true">
        <path d="M4 10h16M4 14h10" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" />
        <rect x="3" y="6" width="18" height="12" rx="2" fill="none" stroke="currentColor" strokeWidth="1.75" />
      </svg>
    );
  }
  if (type === "secure") {
    return (
      <svg viewBox="0 0 24 24" className={className} aria-hidden="true">
        <path d="M12 3 4 6v6c0 4.4 3.4 8.5 8 9 4.6-.5 8-4.6 8-9V6l-8-3Z" fill="none" stroke="currentColor" strokeWidth="1.75" />
      </svg>
    );
  }
  if (type === "flexible") {
    return (
      <svg viewBox="0 0 24 24" className={className} aria-hidden="true">
        <path d="M7 7h10v10H7z" fill="none" stroke="currentColor" strokeWidth="1.75" />
        <path d="M17 7l4-4M7 17l-4 4" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" />
      </svg>
    );
  }
  if (type === "baggage") {
    return (
      <svg viewBox="0 0 24 24" className={className} aria-hidden="true">
        <rect x="6" y="8" width="12" height="12" rx="2" fill="none" stroke="currentColor" strokeWidth="1.75" />
        <path d="M9 8V6a3 3 0 0 1 6 0v2" fill="none" stroke="currentColor" strokeWidth="1.75" />
      </svg>
    );
  }
  return (
    <svg viewBox="0 0 24 24" className={className} aria-hidden="true">
      <circle cx="12" cy="12" r="8" fill="none" stroke="currentColor" strokeWidth="1.75" />
      <path d="M12 8v4l3 2" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" />
    </svg>
  );
}
