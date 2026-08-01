import { cn } from "@/lib/cn";
import type { HomepageTrustChip } from "../types/homepage";

type BenefitStripProps = {
  items: HomepageTrustChip[];
  className?: string;
};

const ICONS = ["fare", "shield", "headset", "spark", "pakistan"] as const;

export function BenefitStrip({ items, className }: BenefitStripProps) {
  if (items.length === 0) return null;

  const displayItems = items.slice(0, 5);

  return (
    <div
      className={cn(
        "grid min-h-12 grid-cols-2 gap-3 border-t border-jp-border/60 pt-4 lg:grid-cols-5 lg:gap-3 lg:border-t-0 lg:pt-0",
        className,
      )}
      data-testid="benefit-strip"
    >
      {displayItems.map((item, index) => (
        <div key={`${item.label}-${index}`} className="flex items-start gap-2.5 text-jp-text">
          <span className="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-jp-primary/30 text-jp-primary">
            <BenefitIcon type={(item.icon as (typeof ICONS)[number]) ?? ICONS[index % ICONS.length]} />
          </span>
          <div className="min-w-0">
            <p className="text-jp-xs font-semibold leading-tight">{item.label}</p>
            {item.description ? (
              <p className="mt-0.5 text-[10px] leading-snug text-jp-muted">{item.description}</p>
            ) : null}
          </div>
        </div>
      ))}
    </div>
  );
}

function BenefitIcon({ type }: { type: (typeof ICONS)[number] }) {
  if (type === "shield") {
    return (
      <svg viewBox="0 0 24 24" className="h-3.5 w-3.5" aria-hidden="true">
        <path d="M12 3 4 6v6c0 4.4 3.4 8.5 8 9 4.6-.5 8-4.6 8-9V6l-8-3Z" fill="none" stroke="currentColor" strokeWidth="1.75" />
      </svg>
    );
  }
  if (type === "fare") {
    return (
      <svg viewBox="0 0 24 24" className="h-3.5 w-3.5" aria-hidden="true">
        <path d="M4 10h16M4 14h10" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" />
        <rect x="3" y="6" width="18" height="12" rx="2" fill="none" stroke="currentColor" strokeWidth="1.75" />
      </svg>
    );
  }
  if (type === "headset") {
    return (
      <svg viewBox="0 0 24 24" className="h-3.5 w-3.5" aria-hidden="true">
        <path d="M4 14v3a2 2 0 0 0 2 2h1v-7H5a1 1 0 0 0-1 1Zm15-5a7 7 0 0 0-14 0v5h14V9Zm3 5h-1v7h1a2 2 0 0 0 2-2v-3a1 1 0 0 0-1-1Z" fill="currentColor" />
      </svg>
    );
  }
  if (type === "pakistan") {
    return (
      <svg viewBox="0 0 24 24" className="h-3.5 w-3.5" aria-hidden="true">
        <circle cx="12" cy="12" r="8" fill="none" stroke="currentColor" strokeWidth="1.75" />
        <path d="M12 4v16M4 12h16" stroke="currentColor" strokeWidth="1.25" />
      </svg>
    );
  }
  return (
    <svg viewBox="0 0 24 24" className="h-3.5 w-3.5" aria-hidden="true">
      <path d="M12 2l2.2 6.8H21l-5.5 4 2.1 6.7L12 15.8 6.4 19.5l2.1-6.7L3 8.8h6.8L12 2Z" fill="currentColor" />
    </svg>
  );
}
