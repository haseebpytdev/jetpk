"use client";

type FlightResultActionsProps = {
  onDetails: () => void;
  onBook: () => void;
  canBook?: boolean;
  booking?: boolean;
  detailsTestId?: string;
  bookTestId?: string;
  detailsLabel?: string;
  bookLabel?: string;
  detailsAriaLabel?: string;
  bookAriaLabel?: string;
};

/**
 * Canonical One-Way result CTA row: Details (outline) + Book Now (primary), side-by-side.
 */
export function FlightResultActions({
  onDetails,
  onBook,
  canBook = true,
  booking = false,
  detailsTestId = "flight-details-trigger",
  bookTestId = "book-now-trigger",
  detailsLabel = "Details",
  bookLabel = "Book Now",
  detailsAriaLabel,
  bookAriaLabel,
}: FlightResultActionsProps) {
  return (
    <div className="flex flex-wrap justify-end gap-2" data-testid="flight-result-actions">
      <button
        type="button"
        className="rounded-jp-md border border-jp-border px-3 py-2 text-sm font-medium text-jp-text hover:border-jp-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-jp-primary"
        data-testid={detailsTestId}
        aria-label={detailsAriaLabel}
        onClick={onDetails}
      >
        {detailsLabel}
      </button>
      <button
        type="button"
        className="rounded-jp-md bg-jp-primary px-4 py-2 text-sm font-semibold text-white hover:opacity-90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-jp-primary focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
        data-testid={bookTestId}
        aria-label={bookAriaLabel}
        disabled={!canBook || booking}
        onClick={onBook}
      >
        {booking ? "…" : bookLabel}
      </button>
    </div>
  );
}
