"use client";

import { useRouter } from "next/navigation";
import { useEffect, useMemo, useState } from "react";
import { fetchNearbyDates } from "../services/flight-results-api";
import type { NearbyDateStripRow } from "../types";
import { resolveNearbyDateResultsPath } from "../utils/nearby-dates";

type NearbyDateStripProps = {
  searchId: string;
  hidden?: boolean;
};

function formatNearbyPrice(amount: number | null): string {
  if (amount === null || amount <= 0) return "Check fares";
  return `PKR ${Math.round(amount).toLocaleString("en-PK")}`;
}

export function NearbyDateStrip({ searchId, hidden }: NearbyDateStripProps) {
  const router = useRouter();
  const [rows, setRows] = useState<NearbyDateStripRow[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (hidden || !searchId) {
      setLoading(false);
      return;
    }

    const controller = new AbortController();
    void fetchNearbyDates({ searchId, signal: controller.signal }).then((response) => {
      if (controller.signal.aborted) return;
      setLoading(false);
      if (!response.ok || !response.data.available) {
        setRows([]);
        return;
      }
      setRows(response.data.dates ?? []);
    });

    return () => controller.abort();
  }, [hidden, searchId]);

  const selectedIndex = useMemo(() => rows.findIndex((row) => row.is_selected), [rows]);
  const prevRow = selectedIndex > 0 ? rows[selectedIndex - 1] : null;
  const nextRow = selectedIndex >= 0 && selectedIndex < rows.length - 1 ? rows[selectedIndex + 1] : null;

  if (hidden || loading || rows.length === 0) {
    return null;
  }

  const go = (row: NearbyDateStripRow | null) => {
    if (!row || row.is_selected) return;
    const nextPath = resolveNearbyDateResultsPath(row.search_url);
    if (!nextPath) return;
    router.push(nextPath);
  };

  return (
    <section
      className="rounded-jp-card border border-jp-border bg-jp-surface p-3 shadow-jp-card"
      aria-label="Nearby departure dates"
      data-testid="nearby-date-strip"
    >
      <div className="mb-2 flex items-center justify-between gap-2">
        <p className="text-[11px] font-semibold uppercase tracking-wide text-jp-text-muted">Nearby dates</p>
        <div className="flex gap-2">
          <button
            type="button"
            data-testid="nearby-date-prev"
            disabled={!prevRow}
            onClick={() => go(prevRow)}
            className="rounded-jp-md border border-jp-border px-2 py-1 text-xs font-semibold text-jp-text disabled:cursor-not-allowed disabled:opacity-40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-jp-primary"
          >
            ← Previous day
          </button>
          <button
            type="button"
            data-testid="nearby-date-next"
            disabled={!nextRow}
            onClick={() => go(nextRow)}
            className="rounded-jp-md border border-jp-border px-2 py-1 text-xs font-semibold text-jp-text disabled:cursor-not-allowed disabled:opacity-40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-jp-primary"
          >
            Next day →
          </button>
        </div>
      </div>
      <div className="flex snap-x snap-mandatory gap-2 overflow-x-auto pb-1 [scrollbar-width:thin]">
        {rows.map((row) => {
          const nextPath = resolveNearbyDateResultsPath(row.search_url);
          const priceLabel = formatNearbyPrice(row.cheapest_pkr);
          return (
            <button
              key={row.date}
              type="button"
              data-testid={row.is_selected ? "nearby-date-current" : `nearby-date-${row.date}`}
              disabled={row.is_selected || !nextPath}
              onClick={() => go(row)}
              className={`min-w-[7.25rem] shrink-0 snap-start rounded-jp-md border px-3 py-2 text-left text-sm transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-jp-primary ${
                row.is_selected
                  ? "border-jp-primary bg-jp-primary/10 font-semibold text-jp-text ring-1 ring-jp-primary/40"
                  : "border-jp-border bg-jp-surface text-jp-text hover:border-jp-primary/60"
              }`}
              aria-current={row.is_selected ? "date" : undefined}
              aria-pressed={row.is_selected}
            >
              <span className="block whitespace-nowrap">{row.label}</span>
              <span className="mt-0.5 block text-xs tabular-nums text-jp-text-muted">{priceLabel}</span>
              {row.is_selected ? (
                <span className="mt-1 block text-[10px] font-semibold uppercase tracking-wide text-jp-primary">
                  Current
                </span>
              ) : null}
            </button>
          );
        })}
      </div>
    </section>
  );
}
