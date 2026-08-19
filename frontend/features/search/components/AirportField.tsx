"use client";

import { IconButton } from "@/components/ui/IconButton";
import { cn } from "@/lib/cn";
import { useDebouncedValue } from "@/lib/hooks/use-debounced-value";
import { useEscapeKey } from "@/lib/hooks/use-escape-key";
import {
  forwardRef,
  useCallback,
  useEffect,
  useId,
  useImperativeHandle,
  useLayoutEffect,
  useRef,
  useState,
} from "react";
import { createPortal } from "react-dom";
import { AirportSearchService } from "@/services/airports";
import { filterAirports } from "../utils/airport-filter";
import type { Airport } from "../types";

type AirportFieldProps = {
  id: string;
  label: string;
  value: Airport | null;
  onChange: (airport: Airport | null) => void;
  /** Fires only after a legitimate suggestion selection (not blur/escape/partial typing). */
  onSelectionComplete?: (airport: Airport) => void;
  placeholder?: string;
  disabled?: boolean;
  className?: string;
  density?: "default" | "compact";
};

export type AirportFieldHandle = {
  focus: () => void;
  focusAndEdit: () => void;
};

export const AirportField = forwardRef<AirportFieldHandle, AirportFieldProps>(function AirportField(
  {
    id,
    label,
    value,
    onChange,
    onSelectionComplete,
    placeholder = "City or airport",
    disabled = false,
    className,
    density = "default",
  },
  ref,
) {
  const listId = useId();
  const rootRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);
  const listRef = useRef<HTMLUListElement>(null);
  const abortRef = useRef<AbortController | null>(null);
  const requestIdRef = useRef(0);
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState(value ? `${value.city} (${value.iata})` : "");
  const [editing, setEditing] = useState(false);
  const [activeIndex, setActiveIndex] = useState(-1);
  const [results, setResults] = useState<Airport[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [listStyle, setListStyle] = useState<React.CSSProperties>({
    position: "fixed",
    top: 0,
    left: 0,
    zIndex: 60,
    visibility: "hidden",
  });
  const editingRef = useRef(false);
  const editValueRef = useRef<Airport | null>(value);
  const onSelectionCompleteRef = useRef(onSelectionComplete);
  onSelectionCompleteRef.current = onSelectionComplete;

  const debouncedQuery = useDebouncedValue(query, 280);

  const closeList = useCallback(() => {
    setOpen(false);
    setActiveIndex(-1);
  }, []);

  const setEditingState = useCallback((next: boolean) => {
    editingRef.current = next;
    setEditing(next);
  }, []);

  const restoreEditing = useCallback(() => {
    const previous = editValueRef.current;
    abortRef.current?.abort();
    abortRef.current = null;
    if (value?.iata !== previous?.iata) onChange(previous);
    setQuery(previous ? `${previous.city} (${previous.iata})` : "");
    setEditingState(false);
    closeList();
  }, [closeList, onChange, setEditingState, value]);

  const handleEscape = useCallback(() => {
    if (editingRef.current) {
      restoreEditing();
    } else {
      closeList();
    }
    inputRef.current?.focus();
  }, [closeList, restoreEditing]);

  useEscapeKey(open, handleEscape);

  const beginEditing = useCallback(() => {
    if (disabled) return;
    if (editingRef.current) {
      setOpen(true);
      return;
    }

    editValueRef.current = value;
    setEditingState(true);
    setQuery("");
    setOpen(true);
    setResults(filterAirports(""));
    setActiveIndex(-1);
    setError(null);
  }, [disabled, setEditingState, value]);

  useImperativeHandle(
    ref,
    () => ({
      focus: () => {
        inputRef.current?.focus();
      },
      focusAndEdit: () => {
        inputRef.current?.focus();
        beginEditing();
      },
    }),
    [beginEditing],
  );

  useEffect(() => {
    if (!editingRef.current) setQuery(value ? `${value.city} (${value.iata})` : "");
  }, [value]);

  useEffect(() => {
    if (value && debouncedQuery === `${value.city} (${value.iata})`) {
      return;
    }

    const localResults = filterAirports(debouncedQuery);
    setResults(localResults);
    setActiveIndex(-1);
    setError(null);

    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;
    const requestId = ++requestIdRef.current;

    const normalized = debouncedQuery.trim();
    if (normalized.length < 2) {
      setLoading(true);
      void AirportSearchService.listPopular(controller.signal).then((result) => {
        if (requestId !== requestIdRef.current) return;
        setLoading(false);
        if (result.ok && result.data.length > 0) {
          setResults(result.data);
          setActiveIndex(-1);
        }
        if (!result.ok && !result.aborted) setError(result.message);
      });
      return () => controller.abort();
    }

    setLoading(true);
    void AirportSearchService.search(normalized, controller.signal).then((result) => {
      if (requestId !== requestIdRef.current) return;
      setLoading(false);
      if (result.ok) {
        if (result.data.length > 0) {
          setResults(result.data);
          setActiveIndex(-1);
        }
        setError(null);
      } else if (!result.aborted) {
        setError(result.message);
      }
    });

    return () => controller.abort();
  }, [debouncedQuery, value]);

  const updateListPosition = useCallback(() => {
    const rect = inputRef.current?.getBoundingClientRect();
    if (!rect) return;
    const viewportPad = 8;
    const gap = 4;
    const width = Math.min(rect.width, window.innerWidth - viewportPad * 2);
    const left = Math.min(
      Math.max(viewportPad, rect.left),
      Math.max(viewportPad, window.innerWidth - width - viewportPad),
    );
    const desiredTop = rect.bottom + gap;
    const maxHeight = Math.max(120, Math.min(240, window.innerHeight - desiredTop - viewportPad));
    const listHeight = listRef.current?.offsetHeight ?? maxHeight;
    const top = Math.min(desiredTop, Math.max(viewportPad, window.innerHeight - listHeight - viewportPad));

    setListStyle({
      position: "fixed",
      top,
      left,
      width,
      zIndex: 60,
      visibility: "visible",
      maxHeight: `${maxHeight}px`,
    });
  }, []);

  useLayoutEffect(() => {
    if (!open) return;
    updateListPosition();
    const raf = window.requestAnimationFrame(updateListPosition);
    window.addEventListener("resize", updateListPosition);
    window.addEventListener("scroll", updateListPosition, true);
    return () => {
      window.cancelAnimationFrame(raf);
      window.removeEventListener("resize", updateListPosition);
      window.removeEventListener("scroll", updateListPosition, true);
    };
  }, [open, results, loading, error, updateListPosition]);

  useEffect(() => {
    if (!open) return;
    const handlePointerDown = (event: MouseEvent) => {
      const target = event.target as Node;
      if (rootRef.current?.contains(target) || listRef.current?.contains(target)) return;
      if (editingRef.current) restoreEditing();
      else closeList();
    };
    document.addEventListener("mousedown", handlePointerDown);
    return () => document.removeEventListener("mousedown", handlePointerDown);
  }, [closeList, open, restoreEditing]);

  const selectAirport = (airport: Airport) => {
    onChange(airport);
    setQuery(`${airport.city} (${airport.iata})`);
    editValueRef.current = airport;
    setEditingState(false);
    closeList();
    const advance = onSelectionCompleteRef.current;
    if (advance) {
      // Defer so React commits closed list / value before focus moves.
      window.requestAnimationFrame(() => advance(airport));
    } else {
      inputRef.current?.focus();
    }
  };

  const handleInputChange = (next: string) => {
    setQuery(next);
    setOpen(true);
    setResults(filterAirports(next));
    setActiveIndex(-1);
  };

  const retrySearch = () => {
    setError(null);
    setQuery((current) => `${current}`);
  };

  const handleKeyDown = (event: React.KeyboardEvent<HTMLInputElement>) => {
    if (!open && (event.key === "ArrowDown" || event.key === "Enter")) {
      setOpen(true);
      return;
    }

    if (!open) return;

    if (event.key === "ArrowDown") {
      event.preventDefault();
      setActiveIndex((index) => (index < 0 ? 0 : Math.min(index + 1, Math.max(results.length - 1, 0))));
    }

    if (event.key === "ArrowUp") {
      event.preventDefault();
      setActiveIndex((index) => (index <= 0 ? -1 : index - 1));
    }

    if (event.key === "Enter" && activeIndex >= 0 && results[activeIndex]) {
      event.preventDefault();
      selectAirport(results[activeIndex]);
    }
  };

  const list = open ? (
    <ul
      ref={listRef}
      id={listId}
      role="listbox"
      aria-label={`${label} suggestions`}
      data-testid="airport-suggestions"
      style={listStyle}
      className="overflow-auto rounded-jp-md border border-jp-border bg-jp-surface py-1 shadow-jp-md"
    >
      {loading ? (
        <li className="px-3 py-2 text-jp-sm text-jp-muted" role="status" aria-live="polite">
          Searching airports…
        </li>
      ) : error ? (
        <li className="px-3 py-2 text-jp-sm">
          <p className="text-jp-danger">{error}</p>
          <button type="button" className="mt-1 text-jp-primary underline" onMouseDown={(e) => e.preventDefault()} onClick={retrySearch}>
            Retry
          </button>
        </li>
      ) : results.length === 0 ? (
        <li className="px-3 py-2 text-jp-sm text-jp-muted">No airports found</li>
      ) : (
        results.map((airport, index) => (
          <li key={airport.iata} role="presentation">
            <button
              type="button"
              role="option"
              id={`${listId}-option-${airport.iata}`}
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
  ) : null;

  return (
    <div ref={rootRef} className={cn("relative min-w-0", className)}>
      <label htmlFor={id} className="mb-1 block text-jp-xs font-semibold uppercase tracking-wide text-jp-text/80">
        {label}
      </label>
      <div className="relative">
        {value && !editing ? (
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
          aria-busy={loading}
          autoComplete="off"
          disabled={disabled}
          value={query}
          placeholder={placeholder}
          onChange={(event) => handleInputChange(event.target.value)}
          onFocus={beginEditing}
          onBlur={(event) => {
            const relatedTarget = event.relatedTarget;
            if (relatedTarget instanceof Node) {
              if (rootRef.current?.contains(relatedTarget) || listRef.current?.contains(relatedTarget)) return;
            }
            // Defer so option mousedown/click and programmatic focus advance can win the race.
            window.setTimeout(() => {
              if (!editingRef.current) {
                closeList();
                return;
              }
              if (
                rootRef.current?.contains(document.activeElement) ||
                listRef.current?.contains(document.activeElement)
              ) {
                return;
              }
              restoreEditing();
            }, 0);
          }}
          onKeyDown={handleKeyDown}
          className={cn(
            "w-full rounded-jp-md border border-jp-border bg-white px-3 text-jp-sm text-jp-text dark:bg-jp-surface",
            density === "compact" ? "min-h-[2.75rem] py-2" : "min-h-jp-tap py-2.5",
            "placeholder:text-jp-muted focus-visible:outline-none focus-visible:shadow-jp-focus",
            value && !editing ? "pl-14" : "",
          )}
          aria-activedescendant={activeIndex >= 0 && results[activeIndex] ? `${listId}-option-${results[activeIndex].iata}` : undefined}
        />
      </div>

      {typeof document !== "undefined" ? createPortal(list, document.body) : null}
    </div>
  );
});

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
