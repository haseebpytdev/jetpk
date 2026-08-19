"use client";

import { cn } from "@/lib/cn";
import { useEscapeKey } from "@/lib/hooks/use-escape-key";
import { useEffect, useId, useLayoutEffect, useMemo, useRef, useState } from "react";
import { createPortal } from "react-dom";
import { addDays, formatDisplayDate, todayIsoDate } from "../utils/dates";

type DateRangeFieldProps = {
  id: string;
  departureDate: string;
  returnDate: string;
  onDepartureChange: (value: string) => void;
  onReturnChange: (value: string) => void;
  min?: string;
  max?: string;
  disabled?: boolean;
  className?: string;
  density?: "default" | "compact";
};

type CalendarDay = {
  iso: string;
  day: number;
  inMonth: boolean;
};

function monthLabel(year: number, month: number): string {
  return new Date(year, month, 1).toLocaleDateString("en-GB", { month: "long", year: "numeric" });
}

function buildMonthGrid(year: number, month: number): CalendarDay[] {
  const firstDay = new Date(year, month, 1);
  const startOffset = (firstDay.getDay() + 6) % 7;
  const gridStart = new Date(year, month, 1 - startOffset);
  const days: CalendarDay[] = [];

  for (let index = 0; index < 42; index += 1) {
    const date = new Date(gridStart);
    date.setDate(gridStart.getDate() + index);
    const isoYear = date.getFullYear();
    const isoMonth = String(date.getMonth() + 1).padStart(2, "0");
    const isoDay = String(date.getDate()).padStart(2, "0");
    days.push({
      iso: `${isoYear}-${isoMonth}-${isoDay}`,
      day: date.getDate(),
      inMonth: date.getMonth() === month,
    });
  }

  return days;
}

function isBetween(iso: string, start: string, end: string): boolean {
  if (!start || !end) return false;
  const rangeStart = start <= end ? start : end;
  const rangeEnd = start <= end ? end : start;
  return iso >= rangeStart && iso <= rangeEnd;
}

export function DateRangeField({
  id,
  departureDate,
  returnDate,
  onDepartureChange,
  onReturnChange,
  min = todayIsoDate(),
  max,
  disabled = false,
  className,
  density = "default",
}: DateRangeFieldProps) {
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

  const anchorDate = departureDate || min;
  const anchor = useMemo(() => {
    const parsed = new Date(`${anchorDate}T00:00:00`);
    return { year: parsed.getFullYear(), month: parsed.getMonth() };
  }, [anchorDate]);

  const [viewYear, setViewYear] = useState(anchor.year);
  const [viewMonth, setViewMonth] = useState(anchor.month);

  useEffect(() => {
    if (!open) return;
    const parsed = new Date(`${(departureDate || min)}T00:00:00`);
    setViewYear(parsed.getFullYear());
    setViewMonth(parsed.getMonth());
  }, [departureDate, min, open]);

  const compact = density === "compact";
  const rangeStart = departureDate;
  const rangeEnd = returnDate;

  const summary = useMemo(() => {
    if (departureDate && returnDate) {
      return `${formatDisplayDate(departureDate)} → ${formatDisplayDate(returnDate)}`;
    }
    if (departureDate) return `${formatDisplayDate(departureDate)} → Return`;
    return "Select departure and return";
  }, [departureDate, returnDate]);

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
    const gap = 8;
    const left = Math.min(Math.max(viewportPad, rect.left), Math.max(viewportPad, window.innerWidth - panelWidth - viewportPad));
    const desiredTop = rect.bottom + gap;
    const panelHeight = panelRef.current?.offsetHeight ?? 360;
    const top = Math.min(desiredTop, Math.max(viewportPad, window.innerHeight - panelHeight - viewportPad));

    setPanelStyle({
      position: "fixed",
      top,
      left,
      zIndex: 60,
      visibility: "visible",
      maxHeight: `min(24rem, ${Math.max(120, window.innerHeight - top - viewportPad)}px)`,
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
  }, [open, viewMonth, viewYear]);

  const handleDaySelect = (iso: string) => {
    if (iso < min || (max && iso > max)) return;

    if (!departureDate || (departureDate && returnDate)) {
      onDepartureChange(iso);
      onReturnChange("");
      return;
    }

    if (iso < departureDate) {
      onDepartureChange(iso);
      onReturnChange("");
      return;
    }

    onReturnChange(iso);
    setOpen(false);
    triggerRef.current?.focus();
  };

  const monthDays = buildMonthGrid(viewYear, viewMonth);

  const panel = open ? (
    <div
      ref={panelRef}
      id={panelId}
      role="dialog"
      aria-label="Departure and return date range"
      data-testid="date-range-panel"
      style={panelStyle}
      className="w-[min(100vw-2rem,20rem)] overflow-y-auto rounded-jp-md border border-jp-border bg-white p-3 shadow-jp-md dark:bg-jp-surface"
    >
      <div className="mb-3 flex items-center justify-between gap-2">
        <button
          type="button"
          aria-label="Previous month"
          className="inline-flex h-8 w-8 items-center justify-center rounded-jp-sm border border-jp-border text-jp-text hover:bg-jp-primary-soft focus-visible:outline-none focus-visible:shadow-jp-focus"
          onClick={() => {
            const date = new Date(viewYear, viewMonth - 1, 1);
            setViewYear(date.getFullYear());
            setViewMonth(date.getMonth());
          }}
        >
          ‹
        </button>
        <p className="text-jp-sm font-semibold text-jp-text">{monthLabel(viewYear, viewMonth)}</p>
        <button
          type="button"
          aria-label="Next month"
          className="inline-flex h-8 w-8 items-center justify-center rounded-jp-sm border border-jp-border text-jp-text hover:bg-jp-primary-soft focus-visible:outline-none focus-visible:shadow-jp-focus"
          onClick={() => {
            const date = new Date(viewYear, viewMonth + 1, 1);
            setViewYear(date.getFullYear());
            setViewMonth(date.getMonth());
          }}
        >
          ›
        </button>
      </div>

      <div className="grid grid-cols-7 gap-1 text-center text-jp-xs font-medium text-jp-muted">
        {["Mo", "Tu", "We", "Th", "Fr", "Sa", "Su"].map((label) => (
          <span key={label}>{label}</span>
        ))}
      </div>

      <div className="mt-1 grid grid-cols-7 gap-1">
        {monthDays.map((day) => {
          const disabledDay = day.iso < min || (max ? day.iso > max : false);
          const selectedStart = day.iso === rangeStart;
          const selectedEnd = day.iso === rangeEnd;
          const inRange =
            rangeStart &&
            rangeEnd &&
            isBetween(day.iso, rangeStart, rangeEnd) &&
            day.iso !== rangeStart &&
            day.iso !== rangeEnd;

          return (
            <button
              key={day.iso}
              type="button"
              disabled={disabledDay}
              aria-label={formatDisplayDate(day.iso)}
              aria-pressed={selectedStart || selectedEnd}
              data-date={day.iso}
              onClick={() => handleDaySelect(day.iso)}
              className={cn(
                "h-9 rounded-jp-sm text-jp-sm transition-colors focus-visible:outline-none focus-visible:shadow-jp-focus",
                !day.inMonth && "text-jp-muted/50",
                disabledDay && "cursor-not-allowed opacity-40",
                inRange && "bg-jp-primary-soft text-jp-text",
                (selectedStart || selectedEnd) && "bg-jp-primary font-semibold text-white",
                !disabledDay && !selectedStart && !selectedEnd && !inRange && "hover:bg-jp-primary-soft/70",
              )}
            >
              {day.day}
            </button>
          );
        })}
      </div>

      <p className="mt-3 text-jp-xs text-jp-muted">
        Select departure first, then return. Return cannot precede departure.
      </p>
    </div>
  ) : null;

  return (
    <div ref={rootRef} className={cn("min-w-0", className)}>
      <label htmlFor={id} className="mb-1 block text-jp-xs font-semibold uppercase tracking-wide text-jp-text/80">
        Departure — Return
      </label>
      <button
        ref={triggerRef}
        id={id}
        type="button"
        disabled={disabled}
        aria-expanded={open}
        aria-controls={panelId}
        data-testid="date-range-trigger"
        onClick={() => setOpen((value) => !value)}
        className={cn(
          "flex w-full items-center justify-between gap-2 rounded-jp-md border border-jp-border bg-white px-3 text-left text-jp-sm text-jp-text dark:bg-jp-surface",
          compact ? "min-h-[2.75rem] py-2" : "min-h-jp-tap py-2.5",
          "focus-visible:outline-none focus-visible:shadow-jp-focus",
        )}
      >
        <span className="truncate">{summary}</span>
        <svg viewBox="0 0 20 20" className="h-4 w-4 shrink-0 text-jp-muted" aria-hidden="true">
          <path d="M5 7.5 10 12.5 15 7.5" fill="none" stroke="currentColor" strokeWidth="1.75" />
        </svg>
      </button>
      {typeof document !== "undefined" ? createPortal(panel, document.body) : null}
      <input type="hidden" name="depart" value={departureDate} readOnly />
      <input type="hidden" name="return_date" value={returnDate} readOnly />
    </div>
  );
}

export function defaultReturnDate(departureDate: string): string {
  if (!departureDate) return "";
  return addDays(departureDate, 7);
}
