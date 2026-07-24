import { cn } from "@/lib/utils";
import type { HTMLAttributes } from "react";

export function MetricCard({
  label,
  value,
  hint,
  className,
}: {
  label: string;
  value: string | number;
  hint?: string;
  className?: string;
}) {
  return (
    <div
      className={cn(
        "rounded-2xl border border-jp-border bg-jp-card px-4 py-3 shadow-sm",
        className,
      )}
    >
      <p className="text-xs font-medium uppercase tracking-wide text-jp-muted">{label}</p>
      <p className="mt-1 text-xl font-semibold tabular-nums text-gray-900">{value}</p>
      {hint ? <p className="mt-0.5 text-[11px] text-jp-muted">{hint}</p> : null}
    </div>
  );
}

export function MetricCardRow({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
  return (
    <div
      className={cn("grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6", className)}
      {...props}
    />
  );
}
