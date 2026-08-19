"use client";

import { cn } from "@/lib/cn";
import { useEscapeKey } from "@/lib/hooks/use-escape-key";
import { useEffect, useId, useLayoutEffect, useRef, useState } from "react";
import { createPortal } from "react-dom";
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
          className="inline-flex h-9 w-9 items-center justify-center rounded-jp-sm border border-jp-border bg-white text-jp-text transition-colors hover:bg-jp-primary-soft disabled:opacity-40 focus-visible:outline-none focus-visible:shadow-jp-focus dark:bg-jp-surface"
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
          className="inline-flex h-9 w-9 items-center justify-center rounded-jp-sm border border-jp-border bg-white text-jp-text transition-colors hover:bg-jp-primary-soft disabled:opacity-40 focus-visible:outline-none focus-visible:shadow-jp-focus dark:bg-jp-surface"
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
}: TravelersCabinSelectorProps) {
  const panelId = useId();
  const triggerRef = useRef<HTMLButtonElement>(null);
  const rootRef = useRef<HTMLDivElement>(null);
  const panelRef = useRef<HTMLDivElement>(null);
  const [open, setOpen] = useState(false);
  const [panelStyle, setPanelStyle] = useState<React.CSSProperties>({
    position: "fixed",
    top: 0,
    left: 0,
    zIndex: 60,
    visibility: "hidden",
  });

  const cabinLabel = CABIN_FIXTURES.find((cabin) => cabin.value === passengers.cabin)?.label ?? "Economy";
  const compactSummary = density === "compact";

  useEscapeKey(open, () => {
    setOpen(false);
    triggerRef.current?.focus();
  });

  useEffect(() => {
    if (!open) return;
    const handlePointerDown = (event: MouseEvent) => {
      const target = event.target as Node;
      if (rootRef.current?.contains(target) || panelRef.current?.contains(target)) return;
      setOpen(false);
    };
    document.addEventListener("mousedown", handlePointerDown);
    return () => document.removeEventListener("mousedown", handlePointerDown);
  }, [open]);

  const updatePortalPosition = () => {
    const rect = rootRef.current?.getBoundingClientRect();
    if (!rect) return;
    const panelWidth = panelRef.current?.offsetWidth ?? 320;
    const viewportPad = 16;
    const left = Math.min(Math.max(viewportPad, rect.left), Math.max(viewportPad, window.innerWidth - panelWidth - viewportPad));
    setPanelStyle({
      position: "fixed",
      top: rect.bottom + 8,
      left,
      zIndex: 60,
      visibility: "visible",
      maxHeight: `min(24rem, ${Math.max(120, window.innerHeight - rect.bottom - viewportPad - 8)}px)`,
      maxWidth: `min(20rem, ${window.innerWidth - viewportPad * 2}px)`,
    });
  };

  useLayoutEffect(() => {
    if (!open) return;
    updatePortalPosition();
    const raf = window.requestAnimationFrame(updatePortalPosition);
    window.addEventListener("resize", updatePortalPosition);
    window.addEventListener("scroll", updatePortalPosition, true);
    return () => {
      window.cancelAnimationFrame(raf);
      window.removeEventListener("resize", updatePortalPosition);
      window.removeEventListener("scroll", updatePortalPosition, true);
    };
  }, [open, passengers]);

  const panel = open ? (
    <div
      ref={panelRef}
      id={panelId}
      role="dialog"
      aria-label="Travelers and cabin selection"
      data-testid="travelers-cabin-panel"
      style={panelStyle}
      className="w-[min(100vw-2rem,20rem)] overflow-y-auto rounded-jp-md border border-jp-border bg-white p-3 shadow-jp-md dark:bg-jp-surface"
    >
      <CounterRow
        label="Adults"
        description="12+"
        value={passengers.adults}
        min={1}
        max={9}
        onDecrement={() => onAdultsChange(passengers.adults - 1)}
        onIncrement={() => onAdultsChange(passengers.adults + 1)}
      />
      <CounterRow
        label="Children"
        description="2–11"
        value={passengers.children}
        min={0}
        max={8}
        onDecrement={() => onChildrenChange(passengers.children - 1)}
        onIncrement={() => onChildrenChange(passengers.children + 1)}
      />
      <CounterRow
        label="Infants"
        description="Under 2"
        value={passengers.infants}
        min={0}
        max={passengers.adults}
        onDecrement={() => onInfantsChange(passengers.infants - 1)}
        onIncrement={() => onInfantsChange(passengers.infants + 1)}
      />
      <fieldset className="mt-2 border-t border-jp-border pt-3">
        <legend className="mb-2 block text-jp-xs font-semibold uppercase tracking-wide text-jp-muted">
          Cabin Class
        </legend>
        <div className="space-y-1">
          {CABIN_FIXTURES.map((cabin) => {
            const selected = passengers.cabin === cabin.value;
            return (
              <label
                key={cabin.value}
                className={cn(
                  "flex cursor-pointer items-center gap-2 rounded-jp-sm px-2 py-2 text-jp-sm transition-colors",
                  selected ? "bg-jp-primary-soft font-semibold text-jp-primary" : "text-jp-text hover:bg-jp-primary-soft/60",
                )}
              >
                <input
                  type="radio"
                  name={`${panelId}-cabin`}
                  value={cabin.value}
                  checked={selected}
                  onChange={() => onCabinChange(cabin.value)}
                  className="h-4 w-4 border-jp-border text-jp-primary accent-jp-brand focus-visible:outline-none focus-visible:shadow-jp-focus"
                />
                <span>{cabin.label}</span>
              </label>
            );
          })}
        </div>
      </fieldset>
    </div>
  ) : null;

  return (
    <div ref={rootRef} className={cn("relative min-w-0", className)}>
      <span className="mb-1 block text-jp-xs font-semibold uppercase tracking-wide text-jp-muted">
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
          "flex w-full items-center justify-between gap-2 rounded-jp-md border border-jp-border bg-white px-3 text-left text-jp-sm dark:bg-jp-surface",
          density === "compact" ? "min-h-[2.75rem] py-2" : "min-h-jp-tap py-2.5",
          "focus-visible:outline-none focus-visible:shadow-jp-focus",
        )}
      >
        <span className="truncate text-jp-text">{passengerSummary(passengers, cabinLabel, compactSummary)}</span>
        <svg viewBox="0 0 20 20" className="h-4 w-4 shrink-0 text-jp-muted" aria-hidden="true">
          <path d="M5 7.5 10 12.5 15 7.5" fill="none" stroke="currentColor" strokeWidth="1.75" />
        </svg>
      </button>
      {typeof document !== "undefined" ? createPortal(panel, document.body) : null}
    </div>
  );
}
