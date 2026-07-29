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
  return (
    <div
      className={cn(
        "sticky top-0 z-30 rounded-jp-card border border-jp-border bg-jp-surface/95 p-3 shadow-jp-card backdrop-blur sm:p-4",
        className,
      )}
      data-testid="search-summary-bar"
    >
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="min-w-0 space-y-1">
          <p className="text-sm font-semibold text-jp-text sm:text-base">
            {summary.origin} → {summary.destination}
            <span className="ml-2 font-normal text-jp-text-muted">· {summary.tripType}</span>
          </p>
          <p className="text-xs text-jp-text-muted sm:text-sm">
            {summary.departureDate}
            {summary.returnDate ? ` – ${summary.returnDate}` : ""}
            {" · "}
            {summary.passengersLabel}
            {" · "}
            <span className="capitalize">{summary.cabin}</span>
            {summary.directOnly ? " · Direct only" : ""}
            {summary.nearbyAirports ? " · Nearby airports" : ""}
            {summary.flexibleDates ? " · Flexible dates" : ""}
          </p>
        </div>
        <button
          type="button"
          className="shrink-0 rounded-jp-md border border-jp-border px-3 py-1.5 text-sm font-medium text-jp-text hover:bg-jp-surface-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-jp-primary"
          onClick={onModifyClick}
        >
          Modify search
        </button>
      </div>
    </div>
  );
}
