"use client";

import { useEffect, useRef } from "react";

type ReturnViewSelectorProps = {
  open: boolean;
  onSelect: (view: "pair" | "segmented") => void;
};

export function ReturnViewSelector({ open, onSelect }: ReturnViewSelectorProps) {
  const firstRef = useRef<HTMLButtonElement>(null);
  const previousFocus = useRef<HTMLElement | null>(null);

  useEffect(() => {
    if (!open) return;
    previousFocus.current = document.activeElement as HTMLElement | null;
    firstRef.current?.focus();

    const onKey = (event: KeyboardEvent) => {
      if (event.key === "Escape") {
        // Do not force Segmented — keep current Pair default / close only via selection.
        return;
      }
    };
    document.addEventListener("keydown", onKey);
    return () => {
      document.removeEventListener("keydown", onKey);
      previousFocus.current?.focus();
    };
  }, [open, onSelect]);

  if (!open) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" data-testid="return-view-selector">
      <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="return-view-title"
        className="w-full max-w-2xl rounded-jp-card border border-jp-border bg-jp-surface p-5 shadow-jp-card"
      >
        <h2 id="return-view-title" className="text-lg font-semibold text-jp-text">
          Choose return flight view
        </h2>
        <p className="mt-1 text-sm text-jp-text-muted">Select how you want to compare outbound and return flights.</p>
        <div className="mt-4 grid gap-3 sm:grid-cols-2">
          <button
            ref={firstRef}
            type="button"
            className="rounded-jp-card border border-jp-border p-4 text-left hover:border-jp-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-jp-primary"
            data-testid="return-view-pair"
            onClick={() => onSelect("pair")}
          >
            <p className="text-base font-semibold text-jp-text">Pair View</p>
            <p className="mt-2 text-sm text-jp-text-muted">
              Compare complete outbound + return combinations together.
            </p>
          </button>
          <button
            type="button"
            className="rounded-jp-card border border-jp-border p-4 text-left hover:border-jp-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-jp-primary"
            data-testid="return-view-segmented"
            onClick={() => onSelect("segmented")}
          >
            <p className="text-base font-semibold text-jp-text">Single / Segmented View</p>
            <p className="mt-2 text-sm text-jp-text-muted">
              Choose your outbound first, then select a compatible return flight.
            </p>
          </button>
        </div>
      </div>
    </div>
  );
}
