import { cn } from "@/lib/cn";

const statusVariants = {
  neutral: "bg-jp-surface-muted text-jp-text",
  success: "bg-jp-success-soft text-jp-success",
  warning: "bg-jp-warning-soft text-jp-warning",
  danger: "bg-jp-danger-soft text-jp-danger",
  info: "bg-jp-info-soft text-jp-info",
  brand: "bg-jp-brand-soft text-jp-brand",
  new: "bg-jp-brand text-white",
} as const;

type StatusBadgeProps = {
  children: React.ReactNode;
  variant?: keyof typeof statusVariants;
  className?: string;
  "data-testid"?: string;
};

export function StatusBadge({ children, variant = "neutral", className, "data-testid": testId }: StatusBadgeProps) {
  return (
    <span
      className={cn(
        "inline-flex items-center rounded-jp-pill px-2 py-0.5 text-jp-xs font-medium",
        statusVariants[variant],
        className,
      )}
      data-testid={testId}
    >
      {children}
    </span>
  );
}
