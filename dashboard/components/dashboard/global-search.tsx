"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import Link from "next/link";
import { fetchDashboardApiBrowser } from "@/lib/read-only/laravel/laravel-browser-client";
import { DASHBOARD_API_ROUTES } from "@/lib/read-only/laravel/api-base";
import type { DashboardSearchResult } from "@/types/dashboard";

type SearchPayload = {
  query: string;
  results: DashboardSearchResult[];
};

export function DashboardGlobalSearch() {
  const [query, setQuery] = useState("");
  const [results, setResults] = useState<DashboardSearchResult[]>([]);
  const [open, setOpen] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const containerRef = useRef<HTMLDivElement>(null);
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const runSearch = useCallback(async (value: string) => {
    const trimmed = value.trim();
    if (trimmed.length < 2) {
      setResults([]);
      setError(null);
      return;
    }

    setLoading(true);
    setError(null);
    try {
      const envelope = await fetchDashboardApiBrowser<SearchPayload>(DASHBOARD_API_ROUTES.search, {
        query: { q: trimmed },
      });
      setResults(envelope.data.results ?? []);
    } catch {
      setResults([]);
      setError("Search unavailable.");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    if (debounceRef.current) {
      clearTimeout(debounceRef.current);
    }
    debounceRef.current = setTimeout(() => {
      runSearch(query);
    }, 280);

    return () => {
      if (debounceRef.current) {
        clearTimeout(debounceRef.current);
      }
    };
  }, [query, runSearch]);

  useEffect(() => {
    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === "Escape") {
        setOpen(false);
      }
    };
    document.addEventListener("keydown", onKeyDown);
    return () => document.removeEventListener("keydown", onKeyDown);
  }, []);

  useEffect(() => {
    if (!open) {
      return undefined;
    }

    const onPointerDown = (event: MouseEvent) => {
      if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
        setOpen(false);
      }
    };

    document.addEventListener("mousedown", onPointerDown);
    return () => document.removeEventListener("mousedown", onPointerDown);
  }, [open]);

  return (
    <div ref={containerRef} className="relative hidden min-w-[200px] flex-1 md:block md:max-w-xl">
      <label className="sr-only" htmlFor="global-search">
        Quick search
      </label>
      <input
        id="global-search"
        type="search"
        value={query}
        onChange={(event) => {
          setQuery(event.target.value);
          setOpen(true);
        }}
        onFocus={() => setOpen(true)}
        placeholder="Search bookings, PNR, customers, agents…"
        className="w-full rounded-xl border border-jp-border bg-white px-4 py-2.5 text-sm text-jp-text"
        autoComplete="off"
      />
      {open && (query.trim().length >= 2 || loading || results.length > 0 || error) ? (
        <div
          className="absolute left-0 right-0 top-full z-50 mt-2 overflow-hidden rounded-xl border border-jp-border bg-white shadow-lg"
          role="listbox"
        >
          {loading ? <p className="px-4 py-3 text-sm text-jp-muted">Searching…</p> : null}
          {error ? <p className="px-4 py-3 text-sm text-red-600">{error}</p> : null}
          {!loading && !error && results.length === 0 ? (
            <p className="px-4 py-3 text-sm text-jp-muted">No matches found.</p>
          ) : null}
          {results.map((result) => (
            <Link
              key={`${result.type}-${result.href}-${result.label}`}
              href={result.href}
              className="block border-t border-jp-border px-4 py-3 text-sm hover:bg-gray-50 focus-visible:bg-gray-50 focus-visible:outline-none"
              role="option"
              onClick={() => setOpen(false)}
            >
              <span className="font-medium text-jp-text">{result.label}</span>
              <span className="mt-0.5 block text-xs text-jp-muted">{result.detail}</span>
            </Link>
          ))}
        </div>
      ) : null}
    </div>
  );
}
