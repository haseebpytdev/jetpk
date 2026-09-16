"use client";

import { cn } from "@/lib/cn";
import { useCallback, useEffect, useId, useLayoutEffect, useMemo, useRef, useState } from "react";

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
  layoverSummary?: string[] | string | null;
  layovers?: LayoverDetail[] | null;
  viaCodes?: string[] | null;
  className?: string;
};

type ParsedLayover = {
  duration: string;
  airportCode: string;
  airportCity: string;
};

function formatMinutes(minutes: number): string {
  if (!Number.isFinite(minutes) || minutes <= 0) return "";
  const hours = Math.floor(minutes / 60);
  const mins = Math.round(minutes % 60);
  if (hours <= 0) return `${mins}m`;
  if (mins <= 0) return `${hours}h`;
  return `${hours}h ${mins}m`;
}

function asStringList(value: unknown): string[] {
  if (Array.isArray(value)) {
    return value
      .map((item) => (typeof item === "string" ? item.trim() : ""))
      .filter((item) => item !== "");
  }
  if (typeof value === "string" && value.trim() !== "") {
    return [value.trim()];
  }
  return [];
}

function parseLayoverLines(lines?: string[] | string | null): ParsedLayover[] {
  const normalized = asStringList(lines);
  if (normalized.length === 0) return [];
  return normalized
    .map((raw) => {
      const text = raw.trim();
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

function fromStructured(layovers?: LayoverDetail[] | null): ParsedLayover[] {
  if (!Array.isArray(layovers) || layovers.length === 0) return [];
  return layovers
    .map((item) => {
      if (!item || typeof item !== "object") return null;
      const code = String(item.airport_code ?? "").trim().toUpperCase();
      const city = String(item.airport_city ?? item.city ?? "").trim();
      const duration =
        String(item.duration_display ?? "").trim() ||
        (typeof item.duration_minutes === "number" ? formatMinutes(item.duration_minutes) : "");
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

function detailsSignature(items: ParsedLayover[]): string {
  return items
    .map((item) => `${item.airportCode}|${item.airportCity}|${item.duration}`)
    .join("||");
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
  const mountedRef = useRef(true);
  const tooltipId = useId();
  const hoverCapableRef = useRef(false);
  const safeStops = Number.isFinite(stops) ? Math.max(0, Math.trunc(stops)) : 0;
  const isDirect = safeStops <= 0;
  const label = isDirect
    ? "Direct"
    : stopsLabel?.trim() || (safeStops === 1 ? "1 Stop" : `${safeStops} Stops`);

  const details = useMemo(() => {
    const parsed = fromStructured(layovers);
    if (parsed.length > 0) return parsed;
    return parseLayoverLines(layoverSummary);
  }, [layovers, layoverSummary]);
  const detailsKey = useMemo(() => detailsSignature(details), [details]);
  const hasDetail = !isDirect && details.length > 0;
  const safeViaCodes = useMemo(
    () => (Array.isArray(viaCodes) ? viaCodes.map((code) => String(code ?? "").trim()).filter(Boolean) : []),
    [viaCodes],
  );

  useEffect(() => {
    mountedRef.current = true;
    return () => {
      mountedRef.current = false;
    };
  }, []);

  useEffect(() => {
    hoverCapableRef.current =
      typeof window !== "undefined" && window.matchMedia("(hover: hover)").matches;
  }, []);

  const updatePlacement = useCallback(() => {
    try {
      const trigger = buttonRef.current;
      const tip = tooltipRef.current;
      if (!trigger || !tip || !mountedRef.current) return;

      const margin = 8;
      const rect = trigger.getBoundingClientRect();
      const tipRect = tip.getBoundingClientRect();
      const vw = window.innerWidth;
      const vh = window.innerHeight;

      let left = rect.left + rect.width / 2;
      let top = rect.bottom + 8;
      let transform = "translate(-50%, 0)";

      if (top + tipRect.height + margin > vh && rect.top - tipRect.height - 8 >= margin) {
        top = rect.top - 8;
        transform = "translate(-50%, -100%)";
      }

      const half = Math.max(tipRect.width / 2, 1);
      if (left - half < margin) {
        left = margin + half;
      } else if (left + half > vw - margin) {
        left = vw - margin - half;
      }

      if (top < margin) top = margin;
      if (top + tipRect.height > vh - margin) {
        top = Math.max(margin, vh - margin - tipRect.height);
      }

      setPlacement((previous) => {
        if (
          previous.left === left &&
          previous.top === top &&
          previous.transform === transform
        ) {
          return previous;
        }
        return { left, top, transform };
      });
    } catch {
      // Tooltip placement is best-effort; never crash the results page.
    }
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
  }, [open, hasDetail, detailsKey, updatePlacement]);

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
    const timer = window.setTimeout(() => {
      document.addEventListener("mousedown", onPointer);
      document.addEventListener("touchstart", onPointer);
    }, 250);
    document.addEventListener("keydown", onKey);
    return () => {
      window.clearTimeout(timer);
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
    <span className={cn("relative inline-flex max-w-full flex-col items-center gap-1", className)}>
      <button
        ref={buttonRef}
        type="button"
        className="rounded-full border border-jp-border bg-jp-surface-muted px-2 py-0.5 text-xs font-medium text-jp-text hover:bg-jp-border-soft focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-jp-primary"
        aria-expanded={hasDetail ? open : undefined}
        aria-controls={hasDetail ? tooltipId : undefined}
        aria-label={ariaLabel}
        onKeyDown={(event) => {
          if (!hasDetail) return;
          if (event.key === "Enter" || event.key === " ") {
            event.preventDefault();
            setOpen((value) => !value);
          }
        }}
        onClick={(event) => {
          if (!hasDetail) return;
          event.preventDefault();
          event.stopPropagation();
          // Pointer click opens; keyboard uses Enter/Space toggle. Avoids focus→click close race.
          setOpen(true);
        }}
        onMouseEnter={() => {
          if (hasDetail && hoverCapableRef.current) setOpen(true);
        }}
        onMouseLeave={() => {
          if (hasDetail && hoverCapableRef.current && document.activeElement !== buttonRef.current) {
            setOpen(false);
          }
        }}
        onFocus={(event) => {
          if (!hasDetail) return;
          // Keyboard focus only — mouse focus + click must not open-then-toggle-closed.
          if (event.currentTarget.matches(":focus-visible")) {
            setOpen(true);
          }
        }}
        onBlur={() => {
          window.setTimeout(() => {
            if (!mountedRef.current) return;
            if (tooltipRef.current?.contains(document.activeElement)) return;
            if (buttonRef.current === document.activeElement) return;
            setOpen(false);
          }, 120);
        }}
      >
        {label}
      </button>
      {safeViaCodes.length > 0 ? (
        <span className="max-w-full truncate text-[10px] text-jp-text-muted">via {safeViaCodes.join(" · ")}</span>
      ) : null}
      {hasDetail && open ? (
        <span
          ref={tooltipRef}
          id={tooltipId}
          role="tooltip"
          className="pointer-events-none fixed z-40 w-max max-w-[min(16rem,calc(100vw-1rem))] overflow-hidden rounded-jp-md border border-[#c5ced8] bg-[#e8edf2] px-3 py-2 text-center text-xs text-jp-text shadow-jp-card"
          style={{ left: placement.left, top: placement.top, transform: placement.transform }}
        >
          {details.map((item, index) => (
            <span key={`${item.airportCode}-${item.airportCity}-${index}`} className="block py-0.5">
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
