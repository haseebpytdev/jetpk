import { cn } from "@/lib/cn";
import type { ReactNode } from "react";

type BadgeProps = {
  children: ReactNode;
  variant?: "new" | "neutral";
  className?: string;
};

export function Badge({ children, variant = "neutral", className }: BadgeProps) {
  return (
    <span
      className={cn(
        "inline-flex items-center rounded-jp-pill px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide",
        variant === "new" && "bg-jp-primary-soft text-jp-primary",
        variant === "neutral" && "bg-jp-surface-muted text-jp-muted",
        className,
      )}
    >
      {children}
    </span>
  );
}
