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
}: DateFieldProps) {
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
          "w-full min-h-jp-tap rounded-jp-md border border-jp-border bg-jp-surface px-3 py-2.5 text-jp-sm text-jp-text",
          "focus-visible:outline-none focus-visible:shadow-jp-focus",
          "[color-scheme:light]",
        )}
      />
      {value ? (
        <p className="mt-1 text-jp-xs text-jp-muted" aria-live="polite">
          {formatDisplayDate(value)}
        </p>
      ) : null}
    </div>
  );
}
