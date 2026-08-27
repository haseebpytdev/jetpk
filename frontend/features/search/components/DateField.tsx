"use client";

import { cn } from "@/lib/cn";
import { forwardRef, useImperativeHandle, useRef } from "react";
import { formatDisplayDate, todayIsoDate } from "../utils/dates";

type DateFieldProps = {
  id: string;
  label: string;
  value: string;
  onChange: (value: string) => void;
  /** Fires after the user commits a date value change. */
  onSelectionComplete?: (value: string) => void;
  min?: string;
  max?: string;
  disabled?: boolean;
  className?: string;
  density?: "default" | "compact";
};

export type DateFieldHandle = {
  focus: () => void;
};

export const DateField = forwardRef<DateFieldHandle, DateFieldProps>(function DateField(
  {
    id,
    label,
    value,
    onChange,
    onSelectionComplete,
    min = todayIsoDate(),
    max,
    disabled = false,
    className,
    density = "default",
  },
  ref,
) {
  const inputRef = useRef<HTMLInputElement>(null);
  const compact = density === "compact";

  useImperativeHandle(
    ref,
    () => ({
      focus: () => {
        inputRef.current?.focus();
      },
    }),
    [],
  );

  return (
    <div className={cn("min-w-0", className)}>
      <label htmlFor={id} className="mb-1 block text-jp-xs font-semibold uppercase tracking-wide text-jp-muted">
        {label}
      </label>
      <input
        ref={inputRef}
        id={id}
        type="date"
        value={value}
        min={min}
        max={max}
        disabled={disabled}
        onChange={(event) => {
          const next = event.target.value;
          onChange(next);
          if (next) onSelectionComplete?.(next);
        }}
        className={cn(
          "w-full rounded-jp-md border border-jp-border bg-white px-3 font-[Inter,system-ui,sans-serif] text-jp-sm text-jp-text dark:bg-jp-surface",
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
});
