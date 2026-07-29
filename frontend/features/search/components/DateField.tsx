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
}: DateFieldProps) {
  const compact = density === "compact";

  return (
    <div className={cn("min-w-0", className)}>
      <label htmlFor={id} className="mb-1 block text-jp-xs font-semibold uppercase tracking-wide text-jp-muted">
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
          "w-full rounded-jp-md border border-jp-border bg-jp-surface px-3 text-jp-sm text-jp-text",
          compact ? "min-h-[2.75rem] py-2" : "min-h-jp-tap py-2.5",
          "focus-visible:outline-none focus-visible:shadow-jp-focus",
          "[color-scheme:light] dark:[color-scheme:dark]",
        )}
      />
      {value && !compact ? (
        <p className="mt-1 text-jp-xs text-jp-muted" aria-live="polite">
          {formatDisplayDate(value)}
        </p>
      ) : null}
    </div>
  );
}
