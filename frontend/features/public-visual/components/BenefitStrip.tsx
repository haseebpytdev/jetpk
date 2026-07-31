import { cn } from "@/lib/cn";
import type { HomepageTrustChip } from "../types/homepage";

type BenefitStripProps = {
  items: HomepageTrustChip[];
  className?: string;
};

const ICONS = ["shield", "lock", "headset", "spark"] as const;

export function BenefitStrip({ items, className }: BenefitStripProps) {
  if (items.length === 0) return null;

  return (
    <div
      className={cn(
        "grid gap-3 border-t border-jp-border/70 pt-jp-md sm:grid-cols-2 lg:flex lg:min-h-12 lg:flex-row lg:items-center lg:justify-between lg:gap-6 lg:border-t-0 lg:py-0",
        className,
      )}
      data-testid="benefit-strip"
    >
      {items.map((item, index) => (
        <div key={`${item.label}-${index}`} className="flex items-center gap-3 text-jp-sm text-jp-text">
          <span className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-jp-primary-soft text-jp-primary">
            <BenefitIcon type={ICONS[index % ICONS.length]} />
          </span>
          <span className="font-medium">{item.label}</span>
        </div>
      ))}
    </div>
  );
}

function BenefitIcon({ type }: { type: (typeof ICONS)[number] }) {
  if (type === "shield") {
    return (
      <svg viewBox="0 0 24 24" className="h-4 w-4" aria-hidden="true">
        <path d="M12 3 4 6v6c0 4.4 3.4 8.5 8 9 4.6-.5 8-4.6 8-9V6l-8-3Z" fill="none" stroke="currentColor" strokeWidth="1.75" />
      </svg>
    );
  }
  if (type === "lock") {
    return (
      <svg viewBox="0 0 24 24" className="h-4 w-4" aria-hidden="true">
        <rect x="5" y="11" width="14" height="10" rx="2" fill="none" stroke="currentColor" strokeWidth="1.75" />
        <path d="M8 11V8a4 4 0 0 1 8 0v3" fill="none" stroke="currentColor" strokeWidth="1.75" />
      </svg>
    );
  }
  if (type === "headset") {
    return (
      <svg viewBox="0 0 24 24" className="h-4 w-4" aria-hidden="true">
        <path d="M4 14v3a2 2 0 0 0 2 2h1v-7H5a1 1 0 0 0-1 1Zm15-5a7 7 0 0 0-14 0v5h14V9Zm3 5h-1v7h1a2 2 0 0 0 2-2v-3a1 1 0 0 0-1-1Z" fill="currentColor" />
      </svg>
    );
  }
  return (
    <svg viewBox="0 0 24 24" className="h-4 w-4" aria-hidden="true">
      <path d="M12 2l2.2 6.8H21l-5.5 4 2.1 6.7L12 15.8 6.4 19.5l2.1-6.7L3 8.8h6.8L12 2Z" fill="currentColor" />
    </svg>
  );
}
