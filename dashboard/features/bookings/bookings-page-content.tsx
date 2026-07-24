import { BookingsWorkspace } from "@/features/bookings/bookings-workspace";
import { Breadcrumb, PageContainer, PageHeader, PreviewDataBanner } from "@/components/ui/page-layout";
import { parseBookingsQuery } from "@/lib/bookings-query";
import { BookingsServiceError, getBookingDetail, getBookingsPage } from "@/services/booking-service";
import { BookingsErrorPanel } from "@/features/bookings/bookings-error-panel";

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
        <PageHeader
          breadcrumb={
            <Breadcrumb items={[{ label: "Home" }, { label: "Operations" }, { label: "Bookings" }]} />
          }
          title="Bookings"
          description="Operational booking list — mock preview data with filters, sorting, and read-only detail."
        />
        <PreviewDataBanner />
        <BookingsWorkspace query={query} result={result} selectedBooking={selectedBooking} />
      </PageContainer>
    );
  } catch (e) {
    if (e instanceof BookingsServiceError) {
      return (
        <PageContainer>
          <PageHeader title="Bookings" />
          <BookingsErrorPanel referenceId={e.referenceId} message={e.message} />
        </PageContainer>
      );
    }
    throw e;
  }
}
