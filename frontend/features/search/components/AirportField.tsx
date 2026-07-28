"use client";

import { IconButton } from "@/components/ui/IconButton";
import { cn } from "@/lib/cn";
import { useEscapeKey } from "@/lib/hooks/use-escape-key";
import { useCallback, useEffect, useId, useRef, useState } from "react";
import { filterAirports } from "../utils/airport-filter";
import type { Airport } from "../types";

type AirportFieldProps = {
  id: string;
  label: string;
  value: Airport | null;
  onChange: (airport: Airport | null) => void;
  placeholder?: string;
  disabled?: boolean;
  className?: string;
};

export function AirportField({
  id,
  label,
  value,
  onChange,
  placeholder = "City or airport",
  disabled = false,
  className,
}: AirportFieldProps) {
  const listId = useId();
  const inputRef = useRef<HTMLInputElement>(null);
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState(value ? `${value.city} (${value.iata})` : "");
  const [activeIndex, setActiveIndex] = useState(0);
  const [results, setResults] = useState<Airport[]>([]);

  const closeList = useCallback(() => {
    setOpen(false);
    setActiveIndex(0);
  }, []);

  useEscapeKey(open, () => {
    closeList();
    inputRef.current?.focus();
  });

  useEffect(() => {
    if (value) {
      setQuery(`${value.city} (${value.iata})`);
    }
  }, [value]);

  useEffect(() => {
    setResults(filterAirports(query));
    setActiveIndex(0);
  }, [query]);

  const selectAirport = (airport: Airport) => {
    onChange(airport);
    setQuery(`${airport.city} (${airport.iata})`);
    closeList();
    inputRef.current?.focus();
  };

  const handleInputChange = (next: string) => {
    setQuery(next);
    setOpen(true);
    if (!next.trim()) onChange(null);
  };

  const handleKeyDown = (event: React.KeyboardEvent<HTMLInputElement>) => {
    if (!open && (event.key === "ArrowDown" || event.key === "Enter")) {
      setOpen(true);
      return;
    }

    if (!open) return;

    if (event.key === "ArrowDown") {
      event.preventDefault();
      setActiveIndex((index) => Math.min(index + 1, Math.max(results.length - 1, 0)));
    }

    if (event.key === "ArrowUp") {
      event.preventDefault();
      setActiveIndex((index) => Math.max(index - 1, 0));
    }

    if (event.key === "Enter" && results[activeIndex]) {
      event.preventDefault();
      selectAirport(results[activeIndex]);
    }

    if (event.key === "Escape") {
      event.preventDefault();
      closeList();
    }
  };

  return (
    <div className={cn("relative min-w-0", className)}>
      <label htmlFor={id} className="mb-1 block text-jp-xs font-semibold uppercase tracking-wide text-jp-muted">
        {label}
      </label>
      <div className="relative">
        {value ? (
          <span className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-jp-sm font-bold text-jp-primary">
            {value.iata}
          </span>
        ) : null}
        <input
          ref={inputRef}
          id={id}
          type="text"
          role="combobox"
          aria-expanded={open}
          aria-controls={listId}
          aria-autocomplete="list"
          aria-label={label}
          autoComplete="off"
          disabled={disabled}
          value={query}
          placeholder={placeholder}
          onChange={(event) => handleInputChange(event.target.value)}
          onFocus={() => setOpen(true)}
          onBlur={(event) => {
            if (!event.currentTarget.parentElement?.parentElement?.contains(event.relatedTarget as Node)) {
              closeList();
            }
          }}
          onKeyDown={handleKeyDown}
          className={cn(
            "w-full min-h-jp-tap rounded-jp-md border border-jp-border bg-jp-surface px-3 py-2.5 text-jp-sm text-jp-text",
            "placeholder:text-jp-muted focus-visible:outline-none focus-visible:shadow-jp-focus",
            value ? "pl-14" : "",
          )}
        />
      </div>

      {open ? (
        <ul
          id={listId}
          role="listbox"
          aria-label={`${label} suggestions`}
          className="absolute z-40 mt-1 max-h-60 w-full overflow-auto rounded-jp-md border border-jp-border bg-jp-surface py-1 shadow-jp-md"
        >
          {results.length === 0 ? (
            <li className="px-3 py-2 text-jp-sm text-jp-muted">No airports found</li>
          ) : (
            results.map((airport, index) => (
              <li key={airport.iata} role="presentation">
                <button
                  type="button"
                  role="option"
                  aria-selected={index === activeIndex}
                  className={cn(
                    "flex w-full items-start gap-3 px-3 py-2 text-left text-jp-sm transition-colors",
                    "hover:bg-jp-primary-soft focus-visible:outline-none focus-visible:shadow-jp-focus",
                    index === activeIndex && "bg-jp-primary-soft",
                  )}
                  onMouseDown={(event) => event.preventDefault()}
                  onClick={() => selectAirport(airport)}
                >
                  <span className="min-w-[2.5rem] font-bold text-jp-primary">{airport.iata}</span>
                  <span>
                    <span className="block font-medium text-jp-text">{airport.city}</span>
                    <span className="block text-jp-xs text-jp-muted">{airport.name}</span>
                  </span>
                </button>
              </li>
            ))
          )}
        </ul>
      ) : null}
    </div>
  );
}

type AirportSwapButtonProps = {
  onSwap: () => void;
  className?: string;
};

export function AirportSwapButton({ onSwap, className }: AirportSwapButtonProps) {
  return (
    <IconButton
      label="Swap origin and destination"
      size="sm"
      onClick={onSwap}
      className={cn("shrink-0 self-end mb-0.5", className)}
    >
      <svg viewBox="0 0 24 24" className="h-4 w-4" aria-hidden="true">
        <path
          d="M7 16V4m0 0L3 8m4-4 4 4M17 8v12m0 0 4-4m-4 4-4-4"
          fill="none"
          stroke="currentColor"
          strokeWidth="2"
          strokeLinecap="round"
          strokeLinejoin="round"
        />
      </svg>
    </IconButton>
  );
}
