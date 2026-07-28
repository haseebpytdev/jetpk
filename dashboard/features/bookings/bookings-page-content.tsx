import { BookingsWorkspace } from "@/features/bookings/bookings-workspace";
import { Breadcrumb, PageContainer, PageHeader } from "@/components/ui/page-layout";
import { DataSourceNoticeSlot, PreviewModeBadgeSlot } from "@/components/dashboard/data-source-notice";
import { parseBookingsQuery } from "@/lib/bookings-query";
import { BookingsServiceError, getBookingDetail, getBookingsPage } from "@/services/booking-service";
import { BookingsErrorPanel } from "@/features/bookings/bookings-error-panel";
import {
  ForbiddenState,
  SanitizedErrorState,
  ServiceUnavailableState,
  UnauthorizedState,
} from "@/components/ui/data-source-status";
import { ReadOnlyServiceError } from "@/lib/read-only/read-only-service";

type Props = {
  searchParams: Promise<Record<string, string | string[] | undefined>>;
};

export async function BookingsPageContent({ searchParams }: Props) {
  const sp = await searchParams;
  const query = parseBookingsQuery(sp);

  try {
    const result = await getBookingsPage(query);
    const selectedBooking = query.selectedId ? await getBookingDetail(query.selectedId) : null;

    return (
      <PageContainer>
        <PreviewModeBadgeSlot />
        <PageHeader
          breadcrumb={
            <Breadcrumb items={[{ label: "Home" }, { label: "Operations" }, { label: "Bookings" }]} />
          }
          title="Bookings"
          description="Operational booking list with filters, sorting, and read-only detail."
        />
        <DataSourceNoticeSlot />
        <BookingsWorkspace query={query} result={result} selectedBooking={selectedBooking} />
      </PageContainer>
    );
  } catch (e) {
    return (
      <PageContainer>
        <PageHeader title="Bookings" />
        <BookingsModuleError error={e} />
      </PageContainer>
    );
  }
}

function BookingsModuleError({ error }: { error: unknown }) {
  if (error instanceof ReadOnlyServiceError) {
    const code = error.envelope.error.code;
    if (code === "unauthenticated") return <UnauthorizedState />;
    if (code === "forbidden") return <ForbiddenState resource="bookings" />;
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
