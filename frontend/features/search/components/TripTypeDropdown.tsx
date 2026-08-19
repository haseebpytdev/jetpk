"use client";

import { cn } from "@/lib/cn";
import { useEscapeKey } from "@/lib/hooks/use-escape-key";
import { useEffect, useId, useLayoutEffect, useRef, useState } from "react";
import { createPortal } from "react-dom";
import type { TripType } from "../types";
import { TRIP_TYPE_LABELS, TRIP_TYPES } from "../hooks/use-search-tabs";

type TripTypeDropdownProps = {
  tripType: TripType;
  onTripTypeChange: (tripType: TripType) => void;
  className?: string;
  compact?: boolean;
};

export function TripTypeDropdown({
  tripType,
  onTripTypeChange,
  className,
  compact = false,
}: TripTypeDropdownProps) {
  const panelId = useId();
  const rootRef = useRef<HTMLDivElement>(null);
  const panelRef = useRef<HTMLDivElement>(null);
  const triggerRef = useRef<HTMLButtonElement>(null);
  const [open, setOpen] = useState(false);
  const [panelStyle, setPanelStyle] = useState<React.CSSProperties>({
    position: "fixed",
    top: 0,
    left: 0,
    zIndex: 60,
    visibility: "hidden",
  });

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
    const panelWidth = panelRef.current?.offsetWidth ?? 216;
    const panelHeight = panelRef.current?.offsetHeight ?? 160;
    const viewportPad = 16;
    const gap = 8;
    const maxRight = window.innerWidth - viewportPad;
    const preferredLeft = rect.right - panelWidth;
    const left = Math.min(Math.max(viewportPad, preferredLeft), Math.max(viewportPad, maxRight - panelWidth));
    const desiredTop = rect.bottom + gap;
    const top = Math.min(desiredTop, Math.max(viewportPad, window.innerHeight - panelHeight - viewportPad));

    setPanelStyle({
      position: "fixed",
      top,
      left,
      zIndex: 60,
      visibility: "visible",
      maxWidth: `min(16rem, ${window.innerWidth - viewportPad * 2}px)`,
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
  }, [open, tripType]);

  const selectTripType = (next: TripType) => {
    onTripTypeChange(next);
    setOpen(false);
    triggerRef.current?.focus();
  };

  const panel = open ? (
    <div
      ref={panelRef}
      id={panelId}
      role="menu"
      data-testid="trip-type-panel"
      style={panelStyle}
      className="min-w-[12rem] overflow-y-auto rounded-jp-md border border-jp-border bg-white p-1.5 shadow-jp-md dark:bg-jp-surface"
    >
      {TRIP_TYPES.map((option) => (
        <button
          key={option}
          type="button"
          role="menuitem"
          onClick={() => selectTripType(option)}
          className={cn(
            "flex w-full rounded-jp-sm px-3 py-2 text-left text-jp-sm text-jp-text transition-colors hover:bg-jp-primary-soft focus-visible:outline-none focus-visible:shadow-jp-focus",
            option === tripType && "bg-jp-primary-soft font-semibold text-jp-primary",
          )}
        >
          {TRIP_TYPE_LABELS[option]}
        </button>
      ))}
    </div>
  ) : null;

  return (
    <div ref={rootRef} className={cn("relative", className)}>
      <button
        ref={triggerRef}
        type="button"
        aria-label="Trip type"
        data-testid="trip-type-trigger"
        aria-haspopup="menu"
        aria-expanded={open}
        aria-controls={panelId}
        onClick={() => setOpen((value) => !value)}
        className={cn(
          "inline-flex items-center gap-2 rounded-jp-md border border-jp-border bg-white px-3 py-2 text-jp-sm font-medium text-jp-text dark:bg-jp-surface",
          compact ? "min-h-[2.75rem]" : "min-h-jp-tap",
          "focus-visible:outline-none focus-visible:shadow-jp-focus",
        )}
      >
        <span className="text-jp-muted">Trip type:</span>
        <span>{TRIP_TYPE_LABELS[tripType]}</span>
        <svg viewBox="0 0 20 20" className="h-4 w-4 text-jp-muted" aria-hidden="true">
          <path d="M5 7.5 10 12.5 15 7.5" fill="none" stroke="currentColor" strokeWidth="1.75" />
        </svg>
      </button>
      {typeof document !== "undefined" ? createPortal(panel, document.body) : null}
    </div>
  );
}
