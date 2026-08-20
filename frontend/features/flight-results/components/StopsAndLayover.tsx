"use client";

import { cn } from "@/lib/cn";
import { useEffect, useId, useRef, useState } from "react";

type StopsAndLayoverProps = {
  stops: number;
  stopsLabel?: string;
  layoverSummary?: string[];
  viaCodes?: string[];
  className?: string;
};

function parseLayoverLine(lines?: string[]): { duration: string; airport: string } | null {
  if (!lines?.length) return null;
  const text = lines[0]?.trim() ?? "";
  const match = text.match(/^(.+?)\s+layover\s*[·•]\s*(.+)$/i);
  if (match) {
    return { duration: match[1].trim(), airport: match[2].trim() };
  }
  const fallback = text.match(/^layover\s*[·•]\s*(.+)$/i);
  if (fallback) {
    return { duration: "", airport: fallback[1].trim() };
  }
  return { duration: text, airport: "" };
}

export function StopsAndLayover({ stops, stopsLabel, layoverSummary, viaCodes, className }: StopsAndLayoverProps) {
  const [open, setOpen] = useState(false);
  const buttonRef = useRef<HTMLButtonElement>(null);
  const tooltipId = useId();
  const isDirect = stops <= 0;
  const label = isDirect ? "Direct" : stops === 1 ? "1 Stop" : `${stops} Stops`;
  const parsed = parseLayoverLine(layoverSummary);
  const hasDetail = !isDirect && Boolean(parsed);

  useEffect(() => {
    if (!open) return;
    const onKey = (event: KeyboardEvent) => {
      if (event.key === "Escape") {
        setOpen(false);
        buttonRef.current?.focus();
      }
    };
    document.addEventListener("keydown", onKey);
    return () => document.removeEventListener("keydown", onKey);
  }, [open]);

  if (isDirect) {
    return (
      <span className={cn("inline-flex rounded-full bg-jp-primary-soft px-2 py-0.5 text-xs font-medium text-jp-primary-active", className)}>
        Direct
      </span>
    );
  }

  const ariaLabel = parsed
    ? [parsed.duration, "layover in", parsed.airport].filter(Boolean).join(" ")
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
        onClick={() => hasDetail && setOpen((value) => !value)}
      >
        {label}
      </button>
      {viaCodes && viaCodes.length > 0 ? (
        <span className="text-[10px] text-jp-text-muted">via {viaCodes.join(" · ")}</span>
      ) : null}
      {hasDetail && open ? (
        <span
          id={tooltipId}
          role="tooltip"
          className="absolute left-1/2 top-full z-20 mt-2 w-max max-w-[14rem] -translate-x-1/2 rounded-jp-md bg-[#e8ecef] px-3 py-2 text-center text-xs text-jp-text shadow-jp-card"
        >
          {parsed?.duration ? <span className="block font-medium">{parsed.duration}</span> : null}
          <span className="block">layover in {parsed?.airport}</span>
        </span>
      ) : null}
    </span>
  );
}
