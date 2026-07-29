import type { StatusPresentation } from "../types/review-payment";
import { statusBadgeClass } from "../utils/status-presentation";

type StatusCardProps = {
  title: string;
  status: StatusPresentation;
  testId?: string;
};

function StatusCard({ title, status, testId }: StatusCardProps) {
  return (
    <article className="rounded-jp-lg border border-jp-border bg-jp-surface p-4" data-testid={testId}>
      <h2 className="text-jp-sm font-semibold text-jp-text">{title}</h2>
      <p className={`mt-2 inline-flex rounded-jp-pill px-3 py-1 text-jp-sm font-medium ${statusBadgeClass(status)}`}>
        <span className="sr-only">{title}: </span>
        {status.label}
      </p>
      <p className="mt-2 text-jp-xs text-jp-muted">Code: {status.code}</p>
    </article>
  );
}

type BookingReferenceCardProps = {
  bookingReference?: string | null;
  pnr?: string | null;
  airlineLocator?: string | null;
};

export function BookingReferenceCard({ bookingReference, pnr, airlineLocator }: BookingReferenceCardProps) {
  return (
    <article className="rounded-jp-lg border border-jp-border bg-jp-surface p-4" data-testid="booking-reference-card">
      <h2 className="text-jp-sm font-semibold text-jp-text">Booking details</h2>
      <dl className="mt-3 space-y-2 text-jp-sm">
        <div>
          <dt className="text-jp-muted">Booking reference</dt>
          <dd className="break-all font-semibold">{bookingReference ?? "—"}</dd>
        </div>
        {pnr ? (
          <div data-testid="pnr-value">
            <dt className="text-jp-muted">PNR</dt>
            <dd className="font-semibold">{pnr}</dd>
          </div>
        ) : null}
        {airlineLocator ? (
          <div data-testid="airline-locator-value">
            <dt className="text-jp-muted">Airline locator</dt>
            <dd className="font-semibold">{airlineLocator}</dd>
          </div>
        ) : null}
      </dl>
    </article>
  );
}

export function PaymentStatusCard({ status }: { status: StatusPresentation }) {
  return <StatusCard title="Payment status" status={status} testId="payment-status-card" />;
}

export function BookingStatusCard({ status }: { status: StatusPresentation }) {
  return <StatusCard title="Booking status" status={status} testId="booking-status-card" />;
}

export function TicketingStatusCard({ status }: { status: StatusPresentation }) {
  return <StatusCard title="Ticketing status" status={status} testId="ticketing-status-card" />;
}

export function PnrCard({ pnr, airlineLocator }: { pnr?: string | null; airlineLocator?: string | null }) {
  if (!pnr && !airlineLocator) return null;

  return (
    <article className="rounded-jp-lg border border-jp-border bg-jp-surface p-4" data-testid="pnr-card">
      <h2 className="text-jp-sm font-semibold text-jp-text">Reservation</h2>
      <dl className="mt-3 space-y-2 text-jp-sm">
        {pnr ? (
          <div>
            <dt className="text-jp-muted">PNR</dt>
            <dd>{pnr}</dd>
          </div>
        ) : null}
        {airlineLocator ? (
          <div>
            <dt className="text-jp-muted">Airline locator</dt>
            <dd>{airlineLocator}</dd>
          </div>
        ) : null}
      </dl>
    </article>
  );
}
