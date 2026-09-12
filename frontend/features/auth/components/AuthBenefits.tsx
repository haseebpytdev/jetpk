import type { AuthBenefitItem } from "../config/auth-benefits";
import { UI_ICON_ACTION_CLASS, UI_ICON_STROKE } from "@/lib/ui-icon";
import {
  Calendar,
  Clock,
  Shield,
  Tag,
  Ticket,
  Users,
  Zap,
} from "lucide-react";

type AuthBenefitsProps = {
  items: AuthBenefitItem[];
  className?: string;
};

export function AuthBenefits({ items, className = "" }: AuthBenefitsProps) {
  return (
    <ul className={`space-y-4 ${className}`} data-testid="auth-benefits">
      {items.map((item) => (
        <li key={item.title} className="flex gap-3">
          <span className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-jp-brand-soft text-jp-brand">
            <BenefitIcon type={item.icon} />
          </span>
          <div>
            <p className="text-jp-sm font-semibold text-jp-text">{item.title}</p>
            <p className="mt-0.5 text-jp-xs text-jp-muted">{item.description}</p>
          </div>
        </li>
      ))}
    </ul>
  );
}

function BenefitIcon({ type }: { type: AuthBenefitItem["icon"] }) {
  const iconProps = {
    className: UI_ICON_ACTION_CLASS,
    strokeWidth: UI_ICON_STROKE,
    "aria-hidden": true as const,
  };

  switch (type) {
    case "ticket":
      return <Ticket {...iconProps} />;
    case "tag":
      return <Tag {...iconProps} />;
    case "clock":
      return <Clock {...iconProps} />;
    case "bolt":
      return <Zap {...iconProps} fill="currentColor" />;
    case "users":
      return <Users {...iconProps} />;
    case "calendar":
      return <Calendar {...iconProps} />;
    default:
      return <Shield {...iconProps} />;
  }
}
