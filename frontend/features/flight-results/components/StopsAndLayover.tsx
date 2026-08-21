"use client";

import { cn } from "@/lib/cn";
import { useCallback, useEffect, useId, useLayoutEffect, useRef, useState } from "react";

export type LayoverDetail = {
  airport_code?: string;
  city?: string;
  airport_city?: string;
  duration_display?: string;
  duration_minutes?: number | null;
};

type StopsAndLayoverProps = {
  stops: number;
  stopsLabel?: string;
  layoverSummary?: string[];
  layovers?: LayoverDetail[];
  viaCodes?: string[];
  className?: string;
};

type ParsedLayover = {
  duration: string;
  airportCode: string;
  airportCity: string;
};

function formatMinutes(minutes: number): string {
  const hours = Math.floor(minutes / 60);
  const mins = minutes % 60;
  if (hours <= 0) return `${mins}m`;
  if (mins <= 0) return `${hours}h`;
  return `${hours}h ${mins}m`;
}

function parseLayoverLines(lines?: string[]): ParsedLayover[] {
  if (!lines?.length) return [];
  return lines
    .map((raw) => {
      const text = raw?.trim() ?? "";
      if (!text) return null;
      const match = text.match(/^(.+?)\s+layover\s*[·•]\s*(.+)$/i);
      if (match) {
        return splitAirport(match[1].trim(), match[2].trim());
      }
      const fallback = text.match(/^layover\s*[·•]\s*(.+)$/i);
      if (fallback) {
        return splitAirport("", fallback[1].trim());
      }
      return splitAirport(text, "");
    })
    .filter((item): item is ParsedLayover => Boolean(item));
}

function splitAirport(duration: string, airportRaw: string): ParsedLayover {
  const cleaned = airportRaw.trim();
  const paren = cleaned.match(/^(.+?)\s*\(([^)]+)\)\s*$/);
  if (paren) {
    return {
      duration,
      airportCity: paren[1].trim(),
      airportCode: paren[2].trim().toUpperCase(),
    };
  }
  if (/^[A-Z]{3}$/i.test(cleaned)) {
    return { duration, airportCity: "", airportCode: cleaned.toUpperCase() };
  }
  return { duration, airportCity: cleaned, airportCode: "" };
}

function fromStructured(layovers?: LayoverDetail[]): ParsedLayover[] {
  if (!layovers?.length) return [];
  return layovers
    .map((item) => {
      const code = (item.airport_code ?? "").trim().toUpperCase();
      const city = (item.airport_city ?? item.city ?? "").trim();
      const duration =
        (item.duration_display ?? "").trim() ||
        (typeof item.duration_minutes === "number" && item.duration_minutes > 0
          ? formatMinutes(item.duration_minutes)
          : "");
      if (!code && !city && !duration) return null;
      return { duration, airportCode: code, airportCity: city };
    })
    .filter((item): item is ParsedLayover => Boolean(item));
}

function airportLine(item: ParsedLayover): string {
  if (item.airportCode && item.airportCity) {
    return `${item.airportCode} · ${item.airportCity}`;
  }
  return item.airportCode || item.airportCity || "Layover airport";
}

export function StopsAndLayover({
  stops,
  stopsLabel,
  layoverSummary,
  layovers,
  viaCodes,
  className,
}: StopsAndLayoverProps) {
  const [open, setOpen] = useState(false);
  const [placement, setPlacement] = useState<{ left: number; top: number; transform: string }>({
    left: 0,
    top: 0,
    transform: "translate(-50%, 0)",
  });
  const buttonRef = useRef<HTMLButtonElement>(null);
  const tooltipRef = useRef<HTMLSpanElement>(null);
  const tooltipId = useId();
  const hoverCapableRef = useRef(false);
  const isDirect = stops <= 0;
  const label = isDirect
    ? "Direct"
    : stopsLabel?.trim() || (stops === 1 ? "1 Stop" : `${stops} Stops`);

  const parsed = fromStructured(layovers);
  const fromSummary = parseLayoverLines(layoverSummary);
  const details = parsed.length > 0 ? parsed : fromSummary;
  const hasDetail = !isDirect && details.length > 0;

  useEffect(() => {
    hoverCapableRef.current =
      typeof window !== "undefined" && window.matchMedia("(hover: hover)").matches;
  }, []);

  const updatePlacement = useCallback(() => {
    const trigger = buttonRef.current;
    const tip = tooltipRef.current;
    if (!trigger || !tip) return;

    const margin = 8;
    const rect = trigger.getBoundingClientRect();
    const tipRect = tip.getBoundingClientRect();
    const vw = window.innerWidth;
    const vh = window.innerHeight;

    let left = rect.left + rect.width / 2;
    let top = rect.bottom + 8;
    let transform = "translate(-50%, 0)";

    // Prefer above when below would clip.
    if (top + tipRect.height + margin > vh && rect.top - tipRect.height - 8 >= margin) {
      top = rect.top - 8;
      transform = "translate(-50%, -100%)";
    }

    const half = tipRect.width / 2;
    if (left - half < margin) {
      left = margin + half;
    } else if (left + half > vw - margin) {
      left = vw - margin - half;
    }

    if (top < margin) top = margin;
    if (top + tipRect.height > vh - margin) {
      top = Math.max(margin, vh - margin - tipRect.height);
    }

    setPlacement({ left, top, transform });
  }, []);

  useLayoutEffect(() => {
    if (!open || !hasDetail) return;
    updatePlacement();
    const onResize = () => updatePlacement();
    window.addEventListener("resize", onResize);
    window.addEventListener("scroll", onResize, true);
    return () => {
      window.removeEventListener("resize", onResize);
      window.removeEventListener("scroll", onResize, true);
    };
  }, [open, hasDetail, details, updatePlacement]);

  useEffect(() => {
    if (!open) return;
    const onKey = (event: KeyboardEvent) => {
      if (event.key === "Escape") {
        setOpen(false);
        buttonRef.current?.focus();
      }
    };
    const onPointer = (event: MouseEvent | TouchEvent) => {
      const target = event.target as Node | null;
      if (!target) return;
      if (buttonRef.current?.contains(target) || tooltipRef.current?.contains(target)) return;
      setOpen(false);
    };
    document.addEventListener("keydown", onKey);
    document.addEventListener("mousedown", onPointer);
    document.addEventListener("touchstart", onPointer);
    return () => {
      document.removeEventListener("keydown", onKey);
      document.removeEventListener("mousedown", onPointer);
      document.removeEventListener("touchstart", onPointer);
    };
  }, [open]);

  if (isDirect) {
    return (
      <span
        className={cn(
          "inline-flex rounded-full bg-jp-primary-soft px-2 py-0.5 text-xs font-medium text-jp-primary-active",
          className,
        )}
      >
        Direct
      </span>
    );
  }

  const ariaLabel = hasDetail
    ? details
        .map((item) =>
          [item.duration ? `Layover ${item.duration}` : "Layover", airportLine(item)]
            .filter(Boolean)
            .join(" "),
        )
        .join("; ")
    : label;

  return (
    <span className={cn("relative inline-flex flex-col items-center gap-1", className)}>
      <button
        ref={buttonRef}
        type="button"
        className="rounded-full border border-jp-border bg-jp-surface-muted px-2 py-0.5 text-xs font-medium text-jp-text hover:bg-jp-border-soft focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-jp-primary"
        aria-expanded={hasDetail ? open : undefined}
        aria-controls={hasDetail ? tooltipId : undefined}
        aria-label={ariaLabel}
        onClick={(event) => {
          if (!hasDetail) return;
          event.preventDefault();
          event.stopPropagation();
          setOpen((value) => !value);
        }}
        onMouseEnter={() => {
          if (hasDetail && hoverCapableRef.current) setOpen(true);
        }}
        onMouseLeave={() => {
          if (hasDetail && hoverCapableRef.current && document.activeElement !== buttonRef.current) {
            setOpen(false);
          }
        }}
        onFocus={() => {
          if (hasDetail) setOpen(true);
        }}
        onBlur={() => {
          window.setTimeout(() => {
            if (tooltipRef.current?.contains(document.activeElement)) return;
            if (buttonRef.current === document.activeElement) return;
            setOpen(false);
          }, 120);
        }}
      >
        {label}
      </button>
      {viaCodes && viaCodes.length > 0 ? (
        <span className="text-[10px] text-jp-text-muted">via {viaCodes.join(" · ")}</span>
      ) : null}
      {hasDetail && open ? (
        <span
          ref={tooltipRef}
          id={tooltipId}
          role="tooltip"
          className="pointer-events-none fixed z-40 w-max max-w-[min(16rem,calc(100vw-1rem))] rounded-jp-md border border-[#c5ced8] bg-[#e8edf2] px-3 py-2 text-center text-xs text-jp-text shadow-jp-card"
          style={{ left: placement.left, top: placement.top, transform: placement.transform }}
        >
          {details.map((item, index) => (
            <span key={`${item.airportCode}-${index}`} className="block py-0.5">
              <span className="block font-semibold text-[#1f2937]">{airportLine(item)}</span>
              <span className="block text-[10px] font-bold uppercase tracking-[0.12em] text-[#64748b]">
                Layover
              </span>
              {item.duration ? (
                <span className="block font-bold text-[#111827]">{item.duration}</span>
              ) : null}
            </span>
          ))}
        </span>
      ) : null}
    </span>
  );
}
