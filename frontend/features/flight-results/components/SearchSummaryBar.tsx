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
        "rounded-jp-card border border-jp-border bg-jp-surface p-3 shadow-jp-card sm:p-3.5",
        className,
      )}
      data-testid="search-summary-bar"
    >
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="min-w-0 space-y-1">
          <p className="text-[11px] font-semibold uppercase tracking-wide text-jp-muted">{summary.tripType}</p>
          <p className="text-base font-semibold tabular-nums text-jp-text sm:text-lg">
            {summary.origin}
            <span className="mx-2 font-normal text-jp-muted" aria-hidden="true">
              →
            </span>
            {summary.destination}
          </p>
          <p className="text-xs text-jp-muted sm:text-sm">
            {summary.departureDate}
            {summary.returnDate ? ` – ${summary.returnDate}` : ""}
          </p>
          <div className="flex flex-wrap gap-1.5 pt-0.5">
            {chips.map((chip) => (
              <span
                key={chip}
                className="rounded-jp-md border border-jp-border bg-jp-surface-muted px-2 py-0.5 text-[11px] font-medium text-jp-text"
              >
                {chip}
              </span>
            ))}
          </div>
        </div>
        <button
          type="button"
          className="shrink-0 rounded-jp-md border border-jp-primary bg-jp-surface px-3 py-2 text-sm font-semibold text-jp-primary hover:bg-jp-primary/5 focus-visible:outline-none focus-visible:shadow-jp-focus"
          onClick={onModifyClick}
          data-testid="edit-search-button"
        >
          Edit search
        </button>
      </div>
    </div>
  );
}
