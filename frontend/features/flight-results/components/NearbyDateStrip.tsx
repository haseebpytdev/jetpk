"use client";

import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { fetchNearbyDates } from "../services/flight-results-api";
import type { NearbyDateStripRow } from "../types";
import { resolveNearbyDateResultsPath } from "../utils/nearby-dates";

type NearbyDateStripProps = {
  searchId: string;
  hidden?: boolean;
};

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

  if (hidden || loading || rows.length === 0) {
    return null;
  }

  return (
    <section
      className="rounded-jp-card border border-jp-border bg-jp-surface p-3 shadow-jp-card"
      aria-label="Nearby departure dates"
      data-testid="nearby-date-strip"
    >
      <p className="mb-2 text-xs font-medium uppercase tracking-wide text-jp-text-muted">Nearby dates</p>
      <div className="flex flex-wrap gap-2">
        {rows.map((row) => {
          const nextPath = resolveNearbyDateResultsPath(row.search_url);
          const priceLabel =
            row.cheapest_pkr !== null ? `${row.cheapest_pkr.toLocaleString()} PKR` : "—";
          return (
            <button
              key={row.date}
              type="button"
              disabled={row.is_selected || !nextPath}
              onClick={() => {
                if (!nextPath || row.is_selected) return;
                router.push(nextPath);
              }}
              className={`min-w-[7rem] rounded-jp-md border px-3 py-2 text-left text-sm transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-jp-primary ${
                row.is_selected
                  ? "border-jp-primary bg-jp-primary/5 font-semibold text-jp-text"
                  : "border-jp-border bg-jp-surface text-jp-text hover:border-jp-primary"
              }`}
              aria-current={row.is_selected ? "true" : undefined}
            >
              <span className="block">{row.label}</span>
              <span className="block text-xs text-jp-text-muted">{priceLabel}</span>
            </button>
          );
        })}
      </div>
    </section>
  );
}
