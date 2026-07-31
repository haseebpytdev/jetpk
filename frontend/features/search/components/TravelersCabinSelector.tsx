"use client";

import { cn } from "@/lib/cn";
import { useEscapeKey } from "@/lib/hooks/use-escape-key";
import { useEffect, useId, useRef, useState } from "react";
import { CABIN_FIXTURES } from "../fixtures/cabins";
import { passengerSummary } from "../hooks/use-passenger-selection";
import type { CabinClass, PassengerSelection } from "../types";

type TravelersCabinSelectorProps = {
  passengers: PassengerSelection;
  onAdultsChange: (value: number) => void;
  onChildrenChange: (value: number) => void;
  onInfantsChange: (value: number) => void;
  onCabinChange: (cabin: CabinClass) => void;
  className?: string;
  density?: "default" | "compact";
  variant?: "default" | "blueprint";
};

function CounterRow({
  label,
  description,
  value,
  min,
  max,
  onDecrement,
  onIncrement,
}: {
  label: string;
  description: string;
  value: number;
  min: number;
  max: number;
  onDecrement: () => void;
  onIncrement: () => void;
}) {
  return (
    <div className="flex items-center justify-between gap-3 py-2">
      <div>
        <p className="text-jp-sm font-medium text-jp-text">{label}</p>
        <p className="text-jp-xs text-jp-muted">{description}</p>
      </div>
      <div className="flex items-center gap-2">
        <button
          type="button"
          aria-label={`Decrease ${label.toLowerCase()}`}
          disabled={value <= min}
          onClick={onDecrement}
          className="inline-flex h-9 w-9 items-center justify-center rounded-jp-sm border border-jp-border text-jp-text transition-colors hover:bg-jp-primary-soft disabled:opacity-40 focus-visible:outline-none focus-visible:shadow-jp-focus"
        >
          −
        </button>
        <span className="min-w-[1.5rem] text-center text-jp-sm font-semibold" aria-live="polite">
          {value}
        </span>
        <button
          type="button"
          aria-label={`Increase ${label.toLowerCase()}`}
          disabled={value >= max}
          onClick={onIncrement}
          className="inline-flex h-9 w-9 items-center justify-center rounded-jp-sm border border-jp-border text-jp-text transition-colors hover:bg-jp-primary-soft disabled:opacity-40 focus-visible:outline-none focus-visible:shadow-jp-focus"
        >
          +
        </button>
      </div>
    </div>
  );
}

export function TravelersCabinSelector({
  passengers,
  onAdultsChange,
  onChildrenChange,
  onInfantsChange,
  onCabinChange,
  className,
  density = "default",
  variant = "default",
}: TravelersCabinSelectorProps) {
  const panelId = useId();
  const triggerRef = useRef<HTMLButtonElement>(null);
  const rootRef = useRef<HTMLDivElement>(null);
  const [open, setOpen] = useState(false);

  const cabinLabel = CABIN_FIXTURES.find((cabin) => cabin.value === passengers.cabin)?.label ?? "Economy";

  useEscapeKey(open, () => {
    setOpen(false);
    triggerRef.current?.focus();
  });

  useEffect(() => {
    if (!open) return;
    const handlePointerDown = (event: MouseEvent) => {
      if (!rootRef.current?.contains(event.target as Node)) setOpen(false);
    };
    document.addEventListener("mousedown", handlePointerDown);
    return () => document.removeEventListener("mousedown", handlePointerDown);
  }, [open]);

  const blueprint = variant === "blueprint";

  return (
    <div ref={rootRef} className={cn("relative min-w-0", className)}>
      <span
        className={cn(
          "mb-1 block font-semibold uppercase tracking-wide text-jp-muted",
          blueprint ? "text-[10px] leading-none" : "text-jp-xs",
        )}
      >
        Travelers &amp; Cabin
      </span>
      <button
        ref={triggerRef}
        type="button"
        aria-label="Travelers and cabin"
        data-testid="travelers-cabin-trigger"
        aria-expanded={open}
        aria-controls={panelId}
        onClick={() => setOpen((value) => !value)}
        className={cn(
          "flex w-full items-center justify-between gap-2 text-left text-jp-sm focus-visible:outline-none",
          blueprint
            ? "min-h-[2rem] border-0 bg-transparent py-1 px-0 shadow-none focus-visible:shadow-none"
            : cn(
                "rounded-jp-md border border-jp-border bg-jp-surface px-3 focus-visible:shadow-jp-focus",
                density === "compact" ? "min-h-[2.75rem] py-2" : "min-h-jp-tap py-2.5",
              ),
        )}
      >
        <span className="truncate text-jp-text">{passengerSummary(passengers, cabinLabel)}</span>
        <svg viewBox="0 0 20 20" className="h-4 w-4 shrink-0 text-jp-muted" aria-hidden="true">
          <path d="M5 7.5 10 12.5 15 7.5" fill="none" stroke="currentColor" strokeWidth="1.75" />
        </svg>
      </button>

      {open ? (
        <div
          id={panelId}
          role="dialog"
          aria-label="Travelers and cabin selection"
          className="absolute z-40 mt-1 w-[min(100%,20rem)] rounded-jp-md border border-jp-border bg-jp-surface p-3 shadow-jp-md"
        >
          <CounterRow
            label="Adults"
            description="Age 12+"
            value={passengers.adults}
            min={1}
            max={9}
            onDecrement={() => onAdultsChange(passengers.adults - 1)}
            onIncrement={() => onAdultsChange(passengers.adults + 1)}
          />
          <CounterRow
            label="Children"
            description="Age 2–11"
            value={passengers.children}
            min={0}
            max={8}
            onDecrement={() => onChildrenChange(passengers.children - 1)}
            onIncrement={() => onChildrenChange(passengers.children + 1)}
          />
          <CounterRow
            label="Infants"
            description="Under 2, on lap"
            value={passengers.infants}
            min={0}
            max={passengers.adults}
            onDecrement={() => onInfantsChange(passengers.infants - 1)}
            onIncrement={() => onInfantsChange(passengers.infants + 1)}
          />
          <div className="mt-2 border-t border-jp-border pt-3">
            <label htmlFor={`${panelId}-cabin`} className="mb-1 block text-jp-xs font-semibold uppercase tracking-wide text-jp-muted">
              Cabin
            </label>
            <select
              id={`${panelId}-cabin`}
              value={passengers.cabin}
              onChange={(event) => onCabinChange(event.target.value as CabinClass)}
              className="w-full min-h-jp-tap rounded-jp-md border border-jp-border bg-jp-surface px-3 py-2 text-jp-sm focus-visible:outline-none focus-visible:shadow-jp-focus"
            >
              {CABIN_FIXTURES.map((cabin) => (
                <option key={cabin.value} value={cabin.value}>
                  {cabin.label}
                </option>
              ))}
            </select>
          </div>
        </div>
      ) : null}
    </div>
  );
}
