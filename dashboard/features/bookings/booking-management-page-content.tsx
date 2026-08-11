import { BookingDetailDrawerContent } from "@/features/bookings/booking-detail-drawer";
import { BookingManagementPanels } from "@/features/bookings/booking-management-panels";
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

type Props = {
  bookingId: string;
};

export async function BookingManagementPageContent({ bookingId }: Props) {
  try {
    const detail = await getBookingManagementDetail(bookingId);
    if (!detail) {
      return (
        <PageContainer>
          <PageHeader title="Booking not found" />
          <EmptyState title="Booking not found" description="The booking reference may be invalid or you may not have access." />
        </PageContainer>
      );
    }

    const booking = detail.summary;

    return (
      <PageContainer data-testid="booking-management-page">
        <PreviewModeBadgeSlot />
        <PageHeader
          breadcrumb={
            <Breadcrumb
              items={[
                { label: "Home" },
                { label: "Booking operations" },
                { label: "Bookings", href: "/bookings" },
                { label: booking.id },
              ]}
            />
          }
          title={`Booking ${booking.id}`}
          description={`PNR ${booking.pnr} · ${booking.origin} → ${booking.destination}`}
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

        <div className="mb-4 flex flex-wrap gap-2">
          <BookingStatusBadge status={booking.bookingStatus} />
          <PaymentStatusBadge status={booking.paymentStatus} />
          <TicketingStatusBadge status={booking.ticketingStatus} />
        </div>

        <div className="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
          <div className="min-w-0 rounded-2xl border border-jp-border bg-white p-5 shadow-sm">
            <BookingDetailDrawerContent booking={booking} />
          </div>
          <aside className="space-y-4">
            <BookingManagementPanels detail={detail} />
            <section className="rounded-2xl border border-jp-border bg-white p-4 shadow-sm">
              <h2 className="text-sm font-semibold text-gray-900">Lifecycle</h2>
              <dl className="mt-3 space-y-2 text-sm">
                <div className="flex justify-between gap-3">
                  <dt className="text-jp-muted">Booking</dt>
                  <dd>
                    <BookingStatusBadge status={booking.bookingStatus} />
                  </dd>
                </div>
                <div className="flex justify-between gap-3">
                  <dt className="text-jp-muted">Payment</dt>
                  <dd>
                    <PaymentStatusBadge status={booking.paymentStatus} />
                  </dd>
                </div>
                <div className="flex justify-between gap-3">
                  <dt className="text-jp-muted">Ticketing</dt>
                  <dd>
                    <TicketingStatusBadge status={booking.ticketingStatus} />
                  </dd>
                </div>
                <div className="flex justify-between gap-3">
                  <dt className="text-jp-muted">Source</dt>
                  <dd className="text-right">{booking.agentOrSource}</dd>
                </div>
                <div className="flex justify-between gap-3">
                  <dt className="text-jp-muted">Last updated</dt>
                  <dd className="text-right text-xs text-jp-muted">{booking.lastUpdated}</dd>
                </div>
              </dl>
            </section>
            <section className="rounded-2xl border border-jp-border bg-white p-4 shadow-sm">
              <h2 className="text-sm font-semibold text-gray-900">Operational actions</h2>
              <div className="mt-3">
                <BookingOperationalActions bookingId={booking.id} defaultCurrency={booking.currency} />
              </div>
            </section>
          </aside>
        </div>
      </PageContainer>
    );
  } catch (error) {
    return (
      <PageContainer>
        <PageHeader title="Booking management" />
        <BookingManagementError error={error} />
      </PageContainer>
    );
  }
}

function BookingManagementError({ error }: { error: unknown }) {
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
