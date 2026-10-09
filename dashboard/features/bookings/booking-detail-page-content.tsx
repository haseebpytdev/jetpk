import { BookingDetailDrawerContent } from "@/features/bookings/booking-detail-drawer";
import { BookingOperationalActions } from "@/features/bookings/booking-operational-actions";
import { Breadcrumb, PageContainer, PageHeader } from "@/components/ui/page-layout";
import { DataSourceNoticeSlot, PreviewModeBadgeSlot } from "@/components/dashboard/data-source-notice";
import {
  BookingStatusBadge,
  PaymentStatusBadge,
  TicketingStatusBadge,
} from "@/components/ui/status-badge";
import { BookingsServiceError, getBookingManagementDetail } from "@/services/booking-service";
import { BookingsErrorPanel } from "@/features/bookings/bookings-error-panel";
import {
  ForbiddenState,
  SanitizedErrorState,
  ServiceUnavailableState,
  UnauthorizedState,
} from "@/components/ui/data-source-status";
import { EmptyState } from "@/components/ui/empty-state";
import { ReadOnlyServiceError } from "@/lib/read-only/read-only-service";
import { DashboardLink as Link } from "@/components/dashboard/dashboard-link";
import { formatCurrency, formatDate, formatDateTime } from "@/lib/format";
import type { BookingDetail } from "@/types/booking";
import type { ReactNode } from "react";

type Props = {
  bookingId: string;
};

const MANAGEMENT_SECTIONS = [
  { id: "booking-overview", label: "Overview" },
  { id: "booking-itinerary", label: "Itinerary" },
  { id: "booking-passengers", label: "Passengers" },
  { id: "booking-fare", label: "Fare" },
  { id: "booking-payments", label: "Payments" },
  { id: "booking-pnr", label: "PNR / supplier" },
  { id: "booking-ticketing", label: "Ticketing" },
  { id: "booking-audit", label: "Audit" },
  { id: "booking-operations", label: "Notes" },
] as const;

function Section({
  id,
  title,
  children,
}: {
  id?: string;
  title: string;
  children: ReactNode;
}) {
  return (
    <section
      id={id}
      className="scroll-mt-24 rounded-2xl border border-jp-border bg-white p-4 shadow-sm"
      data-testid={id ? `booking-section-${id}` : undefined}
    >
      <h2 className="text-sm font-semibold text-gray-900">{title}</h2>
      <div className="mt-3">{children}</div>
    </section>
  );
}

function BookingSectionNav() {
  return (
    <nav
      className="sticky top-0 z-10 -mx-1 mb-4 flex gap-2 overflow-x-auto rounded-2xl border border-jp-border bg-white/95 p-2 backdrop-blur"
      aria-label="Booking management sections"
      data-testid="booking-management-section-nav"
    >
      {MANAGEMENT_SECTIONS.map((item) => (
        <a
          key={item.id}
          href={`#${item.id}`}
          className="shrink-0 rounded-xl px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50"
        >
          {item.label}
        </a>
      ))}
    </nav>
  );
}

function BookingDetailPanels({ detail }: { detail: BookingDetail }) {
  const { passengers, fareSummary, paymentSummary, pnrSummary, ticketReadiness, auditMetadata, itinerary } =
    detail;

  return (
    <div className="space-y-4" data-testid="booking-detail-panels">
      <Section id="booking-itinerary" title="Itinerary">
        <dl className="space-y-2 text-sm">
          <div className="flex justify-between gap-3">
            <dt className="text-jp-muted">Route</dt>
            <dd>{itinerary.route || "—"}</dd>
          </div>
          <div className="flex justify-between gap-3">
            <dt className="text-jp-muted">Airline</dt>
            <dd>{itinerary.airline || "—"}</dd>
          </div>
          <div className="flex justify-between gap-3">
            <dt className="text-jp-muted">Travel date</dt>
            <dd>{itinerary.travelDate ? formatDate(itinerary.travelDate) : "—"}</dd>
          </div>
          <div className="flex justify-between gap-3">
            <dt className="text-jp-muted">Return date</dt>
            <dd>{itinerary.returnDate ? formatDate(itinerary.returnDate) : "—"}</dd>
          </div>
        </dl>
      </Section>

      {passengers.length > 0 ? (
        <Section id="booking-passengers" title="Passengers">
          <ul className="space-y-2 text-sm">
            {passengers.map((passenger, index) => (
              <li key={`${passenger.displayName}-${index}`} className="flex justify-between gap-3">
                <span>{passenger.displayName}</span>
                <span className="capitalize text-jp-muted">{passenger.type}</span>
              </li>
            ))}
          </ul>
        </Section>
      ) : null}

      {fareSummary ? (
        <Section id="booking-fare" title="Fare breakdown">
          <dl className="space-y-2 text-sm">
            <div className="flex justify-between gap-3">
              <dt className="text-jp-muted">Base fare</dt>
              <dd>{formatCurrency(fareSummary.baseFare, fareSummary.currency)}</dd>
            </div>
            <div className="flex justify-between gap-3">
              <dt className="text-jp-muted">Taxes</dt>
              <dd>{formatCurrency(fareSummary.taxes, fareSummary.currency)}</dd>
            </div>
            <div className="flex justify-between gap-3">
              <dt className="text-jp-muted">Fees</dt>
              <dd>{formatCurrency(fareSummary.fees, fareSummary.currency)}</dd>
            </div>
            <div className="flex justify-between gap-3">
              <dt className="text-jp-muted">Markup</dt>
              <dd>{formatCurrency(fareSummary.markup, fareSummary.currency)}</dd>
            </div>
            <div className="flex justify-between gap-3 font-semibold">
              <dt>Total</dt>
              <dd>{formatCurrency(fareSummary.total, fareSummary.currency)}</dd>
            </div>
          </dl>
        </Section>
      ) : null}

      {paymentSummary ? (
        <Section id="booking-payments" title="Payment summary">
          <dl className="space-y-2 text-sm">
            <div className="flex justify-between gap-3">
              <dt className="text-jp-muted">Status</dt>
              <dd className="capitalize">{String(paymentSummary.status)}</dd>
            </div>
            <div className="flex justify-between gap-3">
              <dt className="text-jp-muted">Paid</dt>
              <dd>{formatCurrency(paymentSummary.amountPaid, paymentSummary.currency)}</dd>
            </div>
            <div className="flex justify-between gap-3">
              <dt className="text-jp-muted">Total</dt>
              <dd>{formatCurrency(paymentSummary.totalAmount, paymentSummary.currency)}</dd>
            </div>
            <div className="flex justify-between gap-3">
              <dt className="text-jp-muted">Balance</dt>
              <dd>
                {formatCurrency(
                  Math.max(0, paymentSummary.totalAmount - paymentSummary.amountPaid),
                  paymentSummary.currency,
                )}
              </dd>
            </div>
          </dl>
        </Section>
      ) : null}

      {pnrSummary ? (
        <Section id="booking-pnr" title="PNR / supplier">
          <dl className="space-y-2 text-sm">
            <div className="flex justify-between gap-3">
              <dt className="text-jp-muted">PNR</dt>
              <dd>{pnrSummary.pnr ?? "—"}</dd>
            </div>
            <div className="flex justify-between gap-3">
              <dt className="text-jp-muted">Supplier ref</dt>
              <dd>{pnrSummary.supplierReference ?? "—"}</dd>
            </div>
            <div className="flex justify-between gap-3">
              <dt className="text-jp-muted">Supplier</dt>
              <dd>{pnrSummary.supplier || "—"}</dd>
            </div>
            <div className="flex justify-between gap-3">
              <dt className="text-jp-muted">Channel</dt>
              <dd>{pnrSummary.channel ?? "—"}</dd>
            </div>
            <div className="flex justify-between gap-3">
              <dt className="text-jp-muted">Supplier status</dt>
              <dd className="capitalize">{pnrSummary.supplierStatus?.replaceAll("_", " ") ?? "—"}</dd>
            </div>
          </dl>
        </Section>
      ) : null}

      {ticketReadiness ? (
        <Section id="booking-ticketing" title="Ticket readiness">
          <dl className="space-y-2 text-sm">
            <div className="flex justify-between gap-3">
              <dt className="text-jp-muted">Ticketing</dt>
              <dd className="capitalize">{String(ticketReadiness.ticketingStatus)}</dd>
            </div>
            <div className="flex justify-between gap-3">
              <dt className="text-jp-muted">Ticket count</dt>
              <dd>{ticketReadiness.ticketCount}</dd>
            </div>
          </dl>
        </Section>
      ) : null}

      {auditMetadata ? (
        <Section id="booking-audit" title="Audit">
          <dl className="space-y-2 text-sm">
            <div className="flex justify-between gap-3">
              <dt className="text-jp-muted">Created</dt>
              <dd>{auditMetadata.createdAt ? formatDateTime(auditMetadata.createdAt) : "—"}</dd>
            </div>
            <div className="flex justify-between gap-3">
              <dt className="text-jp-muted">Updated</dt>
              <dd>{auditMetadata.updatedAt ? formatDateTime(auditMetadata.updatedAt) : "—"}</dd>
            </div>
            <div className="flex justify-between gap-3">
              <dt className="text-jp-muted">Booking status</dt>
              <dd className="capitalize">{String(auditMetadata.bookingStatus)}</dd>
            </div>
          </dl>
        </Section>
      ) : null}

      <Section id="booking-operations" title="Operational actions">
        <BookingOperationalActions bookingId={detail.summary.id} />
      </Section>
    </div>
  );
}

export async function BookingDetailPageContent({ bookingId }: Props) {
  try {
    const detail = await getBookingManagementDetail(bookingId);
    if (!detail) {
      return (
        <PageContainer>
          <PageHeader title="Booking not found" />
          <EmptyState
            title="Booking not found"
            description="The booking reference may be invalid or you may not have access."
          />
        </PageContainer>
      );
    }

    const booking = detail.summary;

    return (
      <PageContainer data-testid="booking-detail-page">
        <PreviewModeBadgeSlot />
        <PageHeader
          breadcrumb={
            <Breadcrumb
              items={[
                { label: "Home" },
                { label: "Operations" },
                { label: "Bookings", href: "/bookings" },
                { label: booking.id },
              ]}
            />
          }
          title={`Booking ${booking.id}`}
          description={`PNR ${booking.pnr || "—"} · ${booking.origin} → ${booking.destination}`}
          actions={
            <Link
              href="/bookings"
              className="inline-flex min-h-11 items-center rounded-xl border border-jp-border bg-white px-3 py-2 text-sm font-medium text-gray-900 hover:bg-gray-50"
            >
              Back to list
            </Link>
          }
        />
        <DataSourceNoticeSlot />

        <BookingSectionNav />

        <div
          id="booking-overview"
          className="mb-4 flex flex-wrap gap-2 scroll-mt-24"
          data-testid="booking-section-booking-overview"
        >
          <BookingStatusBadge status={booking.bookingStatus} />
          <PaymentStatusBadge status={booking.paymentStatus} />
          <TicketingStatusBadge status={booking.ticketingStatus} />
        </div>

        <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
          <div className="min-w-0 rounded-2xl border border-jp-border bg-white p-5 shadow-sm">
            <BookingDetailDrawerContent
              booking={booking}
              showOperationalActions={false}
              showFullDetailLink={false}
            />
          </div>
          <div className="min-w-0 space-y-4">
            <BookingDetailPanels detail={detail} />
          </div>
        </div>
      </PageContainer>
    );
  } catch (error) {
    return (
      <PageContainer>
        <PageHeader title="Booking detail" />
        <BookingDetailError error={error} />
      </PageContainer>
    );
  }
}

function BookingDetailError({ error }: { error: unknown }) {
  if (error instanceof ReadOnlyServiceError) {
    const code = error.envelope.error.code;
    if (code === "unauthenticated") return <UnauthorizedState />;
    if (code === "forbidden") return <ForbiddenState resource="booking" />;
    if (code === "unavailable") return <ServiceUnavailableState />;
    return (
      <SanitizedErrorState
        message={error.envelope.error.message}
        referenceId={error.envelope.error.referenceIdSafe}
      />
    );
  }
  if (error instanceof BookingsServiceError) {
    return <BookingsErrorPanel referenceId={error.referenceId} message={error.message} />;
  }
  throw error;
}
