"use client";

import { cn } from "@/lib/cn";
import { formatDisplayDate, todayIsoDate } from "../utils/dates";

type DateFieldProps = {
  id: string;
  label: string;
  value: string;
  onChange: (value: string) => void;
  min?: string;
  max?: string;
  disabled?: boolean;
  className?: string;
  density?: "default" | "compact";
  variant?: "default" | "blueprint";
};

export function DateField({
  id,
  label,
  value,
  onChange,
  min = todayIsoDate(),
  max,
  disabled = false,
  className,
  density = "default",
  variant = "default",
}: DateFieldProps) {
  const compact = density === "compact";
  const blueprint = variant === "blueprint";

  return (
    <div className={cn("min-w-0", className)}>
      <label
        htmlFor={id}
        className={cn(
          "mb-1 block font-semibold uppercase tracking-wide text-jp-muted",
          blueprint ? "text-[10px] leading-none" : "text-jp-xs",
        )}
      >
        {label}
      </label>
      <input
        id={id}
        type="date"
        value={value}
        min={min}
        max={max}
        disabled={disabled}
        onChange={(event) => onChange(event.target.value)}
        className={cn(
          "w-full text-jp-sm text-jp-text focus-visible:outline-none",
          blueprint
            ? "min-h-[2rem] border-0 bg-transparent py-1 px-0 shadow-none focus-visible:shadow-none"
            : cn(
                "rounded-jp-md border border-jp-border bg-jp-surface px-3 focus-visible:shadow-jp-focus",
                compact ? "min-h-[2.75rem] py-2" : "min-h-jp-tap py-2.5",
                "[color-scheme:light] dark:[color-scheme:dark]",
              ),
        )}
      />
      {value && !compact && !blueprint ? (
        <p className="mt-1 text-jp-xs text-jp-muted" aria-live="polite">
          {formatDisplayDate(value)}
        </p>
      ) : null}
    </div>
  );
}
