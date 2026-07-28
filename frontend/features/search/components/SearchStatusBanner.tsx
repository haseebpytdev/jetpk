"use client";

import type { SearchSubmitState } from "@/services/flight-search";

type SearchStatusBannerProps = {
  state: SearchSubmitState;
};

export function SearchStatusBanner({ state }: SearchStatusBannerProps) {
  if (state.status === "idle") return null;

  if (state.status === "submitting") {
    return (
      <div
        className="mt-4 rounded-jp-md border border-jp-border bg-jp-surface-muted px-4 py-3 text-jp-sm text-jp-text"
        role="status"
        aria-live="polite"
        data-testid="search-submit-status"
      >
        <p className="font-semibold text-jp-primary">Searching flights…</p>
        <p className="mt-1 text-jp-muted">Connecting to JetPakistan search.</p>
      </div>
    );
  }

  if (state.status === "redirecting") {
    return (
      <div
        className="mt-4 rounded-jp-md border border-jp-primary-border bg-jp-primary-soft px-4 py-3 text-jp-sm text-jp-text"
        role="status"
        aria-live="polite"
        data-testid="search-submit-status"
      >
        <p className="font-semibold text-jp-primary">Search accepted — opening results…</p>
      </div>
    );
  }

  return (
    <div
      className="mt-4 rounded-jp-md border border-red-200 bg-red-50 px-4 py-3 text-jp-sm text-red-800"
      role="alert"
      data-testid="search-submit-status"
    >
      <p className="font-semibold">{state.message}</p>
    </div>
  );
}
