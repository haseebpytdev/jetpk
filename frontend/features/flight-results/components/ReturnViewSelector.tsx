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
  }, [open]);

  if (!open) return null;

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
      data-testid="return-view-selector"
    >
      <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="return-view-title"
        className="w-full max-w-xl rounded-jp-card border border-jp-border bg-jp-surface p-5 shadow-jp-card"
      >
        <h2 id="return-view-title" className="text-lg font-semibold text-jp-text">
          Choose how you&apos;d like to view your return flights
        </h2>
        <div className="mt-4 grid gap-3 sm:grid-cols-2">
          <button
            ref={firstRef}
            type="button"
            className="rounded-jp-card border border-jp-border p-4 text-left transition-colors hover:border-jp-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-jp-primary"
            data-testid="return-view-pair"
            onClick={() => onSelect("pair")}
          >
            <div className="mb-3 flex h-10 w-10 items-center justify-center rounded-jp-md bg-jp-surface-muted text-jp-primary" aria-hidden="true">
              <svg viewBox="0 0 24 24" className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="1.75">
                <path d="M4 8h16M4 16h16" strokeLinecap="round" />
                <circle cx="8" cy="8" r="1.5" fill="currentColor" stroke="none" />
                <circle cx="16" cy="16" r="1.5" fill="currentColor" stroke="none" />
              </svg>
            </div>
            <p className="text-base font-semibold text-jp-text">Paired view</p>
            <p className="mt-1.5 text-sm text-jp-text-muted">See outbound and return combinations together.</p>
          </button>
          <button
            type="button"
            className="rounded-jp-card border border-jp-border p-4 text-left transition-colors hover:border-jp-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-jp-primary"
            data-testid="return-view-segmented"
            onClick={() => onSelect("segmented")}
          >
            <div className="mb-3 flex h-10 w-10 items-center justify-center rounded-jp-md bg-jp-surface-muted text-jp-primary" aria-hidden="true">
              <svg viewBox="0 0 24 24" className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="1.75">
                <path d="M5 7h14M5 12h10M5 17h14" strokeLinecap="round" />
              </svg>
            </div>
            <p className="text-base font-semibold text-jp-text">Segmented view</p>
            <p className="mt-1.5 text-sm text-jp-text-muted">Choose your outbound first, then select your return.</p>
          </button>
        </div>
      </div>
    </div>
  );
}
