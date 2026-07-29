import Link from "next/link";

type StateCardProps = {
  title: string;
  message: string;
  actionHref?: string;
  actionLabel?: string;
  testId?: string;
};

function StateCard({ title, message, actionHref, actionLabel, testId }: StateCardProps) {
  return (
    <div className="rounded-jp-lg border border-jp-border bg-jp-surface p-6" data-testid={testId}>
      <h1 className="text-xl font-semibold text-jp-text">{title}</h1>
      <p className="mt-2 text-jp-sm text-jp-muted">{message}</p>
      {actionHref && actionLabel ? (
        <Link
          href={actionHref}
          className="mt-4 inline-flex items-center justify-center rounded-jp-md bg-jp-primary px-4 py-2 text-jp-sm font-semibold text-white focus-visible:shadow-jp-focus"
        >
          {actionLabel}
        </Link>
      ) : null}
    </div>
  );
}

export function BookingSessionExpiredState() {
  return (
    <StateCard
      testId="booking-session-expired"
      title="Checkout session expired"
      message="Your checkout session has expired. Please search again and select your flight."
      actionHref="/"
      actionLabel="Return to search"
    />
  );
}

export function OfferExpiredState({ redirectUrl }: { redirectUrl?: string | null }) {
  return (
    <StateCard
      testId="offer-expired"
      title="Fare no longer available"
      message="This fare is no longer available. Please refresh results and select another option."
      actionHref={redirectUrl ?? "/flights/results"}
      actionLabel="Back to results"
    />
  );
}

export function MissingBookingSessionState() {
  return (
    <StateCard
      testId="missing-booking-session"
      title="No flight selected"
      message="Please search for a flight and select a fare before entering passenger details."
      actionHref="/"
      actionLabel="Start search"
    />
  );
}

export function SupplierRequirementsUnavailableState() {
  return (
    <StateCard
      testId="supplier-requirements-unavailable"
      title="Passenger requirements unavailable"
      message="We could not load passenger requirements for this booking. Please try again or contact support."
      actionHref="/support"
      actionLabel="Contact support"
    />
  );
}

export function SeatExtrasReadinessPanel({ message }: { message: string }) {
  return (
    <div
      className="rounded-jp-md border border-jp-border bg-jp-surface-muted p-3 text-jp-sm text-jp-muted"
      data-testid="seat-extras-readiness"
      role="note"
    >
      {message}
    </div>
  );
}
