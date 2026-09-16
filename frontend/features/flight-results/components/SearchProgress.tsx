type SearchProgressProps = {
  message: string;
  summary?: {
    origin: string;
    destination: string;
    departureDate: string;
    returnDate?: string;
    passengersLabel: string;
    cabin: string;
    tripType: string;
  };
  compact?: boolean;
};

export function SearchProgress({ message, summary, compact = false }: SearchProgressProps) {
  if (compact) {
    return (
      <div
        className="flex items-center gap-2 rounded-jp-md border border-jp-border bg-jp-surface-muted px-3 py-2 text-sm text-jp-text"
        role="status"
        aria-live="polite"
        data-testid="search-progress-compact"
      >
        <span
          className="inline-block h-3.5 w-3.5 animate-spin rounded-full border-2 border-jp-primary border-t-transparent motion-reduce:animate-none"
          aria-hidden="true"
        />
        <span>{message}</span>
      </div>
    );
  }

  return (
    <div
      className="overflow-hidden rounded-jp-card border border-jp-border bg-jp-surface"
      role="status"
      aria-live="polite"
      data-testid="search-progress"
    >
      <div className="border-b border-jp-border bg-jp-surface-muted px-4 py-3">
        {summary ? (
          <div
            className="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-jp-text"
            data-testid="search-progress-route"
          >
            <span className="font-semibold">
              {summary.origin} <span aria-hidden="true">→</span> {summary.destination}
            </span>
            <span className="text-jp-muted">
              {summary.departureDate}
              {summary.returnDate ? ` · ${summary.returnDate}` : ""}
            </span>
            <span className="text-jp-muted">
              {summary.passengersLabel} · {summary.cabin} · {summary.tripType}
            </span>
          </div>
        ) : null}
        <p className="mt-1 text-sm font-medium text-jp-text" data-testid="search-progress-message">
          {message}
        </p>
      </div>
      <div className="h-1 overflow-hidden bg-jp-border-soft" aria-hidden="true">
        <div className="h-full w-1/3 animate-pulse bg-jp-primary/70 motion-reduce:animate-none" />
      </div>
    </div>
  );
}
