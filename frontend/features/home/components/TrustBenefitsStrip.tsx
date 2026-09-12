import { cn } from "@/lib/cn";
import { UI_ICON_ACTION_CLASS, UI_ICON_STROKE } from "@/lib/ui-icon";
import { Headphones, Heart, Shield, Ticket } from "lucide-react";
import { BENEFIT_FIXTURES } from "../fixtures/benefits";

type TrustBenefitsStripProps = {
  className?: string;
};

function BenefitIcon({ type }: { type: (typeof BENEFIT_FIXTURES)[number]["icon"] }) {
  const iconProps = {
    className: cn(UI_ICON_ACTION_CLASS, "text-jp-primary"),
    strokeWidth: UI_ICON_STROKE,
    "aria-hidden": true as const,
  };

  switch (type) {
    case "shield":
      return <Shield {...iconProps} />;
    case "headset":
      return <Headphones {...iconProps} />;
    case "fare":
      return <Ticket {...iconProps} />;
    case "pakistan":
      return <Heart {...iconProps} />;
    default:
      return <Shield {...iconProps} />;
  }
}

export function TrustBenefitsStrip({ className }: TrustBenefitsStripProps) {
  return (
    <ul className={cn("grid gap-3 sm:grid-cols-2 xl:grid-cols-4", className)}>
      {BENEFIT_FIXTURES.map((benefit) => (
        <li
          key={benefit.id}
          className="flex items-start gap-3 rounded-jp-md border border-jp-border-soft bg-jp-surface-muted/80 px-3 py-2.5"
        >
          <span className="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-jp-primary-soft">
            <BenefitIcon type={benefit.icon} />
          </span>
          <span>
            <span className="block text-jp-sm font-semibold text-jp-text">{benefit.title}</span>
            <span className="block text-jp-xs text-jp-muted">{benefit.description}</span>
          </span>
        </li>
      ))}
    </ul>
  );
}
