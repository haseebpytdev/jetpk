import { cn } from "@/lib/cn";
import { UI_ICON_STROKE } from "@/lib/ui-icon";
import { Headphones, Lock, Shield, Sparkles } from "lucide-react";
import type { HomepageTrustChip } from "../types/homepage";

type BenefitStripProps = {
  items: HomepageTrustChip[];
  className?: string;
  variant?: "default" | "hero";
};

const ICONS = ["shield", "lock", "headset", "spark"] as const;

export function BenefitStrip({ items, className, variant = "default" }: BenefitStripProps) {
  if (items.length === 0) return null;

  const onHero = variant === "hero";

  return (
    <div
      className={cn(
        "grid gap-3 border-t pt-jp-md sm:grid-cols-2 lg:grid-cols-4",
        onHero ? "border-white/18" : "border-jp-border/70",
        className,
      )}
      data-testid="benefit-strip"
    >
      {items.map((item, index) => (
        <div
          key={`${item.label}-${index}`}
          className={cn(
            "flex items-center gap-3 rounded-jp-md px-3 py-2.5 text-jp-sm",
            onHero
              ? "border border-white/22 bg-black/48 text-white shadow-sm backdrop-blur-md"
              : "text-jp-text",
          )}
        >
          <span
            className={cn(
              "inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full",
              onHero ? "bg-white/14 text-white" : "bg-jp-primary-soft text-jp-primary",
            )}
          >
            <BenefitIcon type={ICONS[index % ICONS.length]} />
          </span>
          <span className={cn("font-medium", onHero ? "text-white" : undefined)}>{item.label}</span>
        </div>
      ))}
    </div>
  );
}

function BenefitIcon({ type }: { type: (typeof ICONS)[number] }) {
  const iconProps = {
    className: "h-4 w-4",
    strokeWidth: UI_ICON_STROKE,
    "aria-hidden": true as const,
  };

  if (type === "shield") return <Shield {...iconProps} />;
  if (type === "lock") return <Lock {...iconProps} />;
  if (type === "headset") return <Headphones {...iconProps} fill="currentColor" />;
  return <Sparkles {...iconProps} fill="currentColor" />;
}
