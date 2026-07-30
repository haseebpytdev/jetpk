import { cn } from "@/lib/cn";

export type SearchSummaryData = {
  origin: string;
  destination: string;
  tripType: string;
  departureDate: string;
  returnDate?: string;
  passengersLabel: string;
  cabin: string;
  directOnly: boolean;
  nearbyAirports: boolean;
  flexibleDates: boolean;
};

type SearchSummaryBarProps = {
  summary: SearchSummaryData;
  onModifyClick: () => void;
  className?: string;
};

export function SearchSummaryBar({ summary, onModifyClick, className }: SearchSummaryBarProps) {
  const chips: string[] = [
    summary.passengersLabel,
    summary.cabin,
    summary.directOnly ? "Direct flights only" : "",
    summary.nearbyAirports ? "Nearby airports" : "",
    summary.flexibleDates ? "Flexible dates" : "",
  ].filter(Boolean);

  return (
    <div
      className={cn(
        "sticky top-0 z-30 rounded-jp-card border border-jp-border bg-jp-surface/95 p-3 shadow-jp-card backdrop-blur sm:p-4",
        className,
      )}
      data-testid="search-summary-bar"
    >
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="min-w-0 space-y-1.5">
          <p className="text-jp-xs font-medium uppercase tracking-wide text-jp-muted">{summary.tripType}</p>
          <p className="text-jp-base font-semibold text-jp-text sm:text-lg">
            {summary.origin}
            <span className="mx-2 font-normal text-jp-muted" aria-hidden="true">→</span>
            {summary.destination}
          </p>
          <p className="text-jp-xs text-jp-muted sm:text-jp-sm">
            {summary.departureDate}
            {summary.returnDate ? ` – ${summary.returnDate}` : ""}
          </p>
          <div className="flex flex-wrap gap-1.5">
            {chips.map((chip) => (
              <span
                key={chip}
                className="rounded-jp-pill border border-jp-border bg-jp-surface-muted px-2 py-0.5 text-[0.65rem] font-medium text-jp-text sm:text-jp-xs"
              >
                {chip}
              </span>
            ))}
          </div>
        </div>
        <button
          type="button"
          className="shrink-0 rounded-jp-md border border-jp-primary-border bg-jp-surface px-3 py-1.5 text-jp-sm font-medium text-jp-primary hover:bg-jp-primary-soft focus-visible:outline-none focus-visible:shadow-jp-focus"
          onClick={onModifyClick}
          data-testid="edit-search-button"
        >
          Edit search
        </button>
      </div>
    </div>
  );
}
