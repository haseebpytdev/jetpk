import { PnrDetailDrawerContent } from "@/features/pnrs/pnr-detail-drawer";
import { pnrHasLinkedBooking, pnrViewBookingPath } from "@/features/pnrs/pnr-booking-link";
import { Breadcrumb, PageContainer, PageHeader } from "@/components/ui/page-layout";
import { DataSourceNoticeSlot, PreviewModeBadgeSlot } from "@/components/dashboard/data-source-notice";
import { DashboardLink as Link } from "@/components/dashboard/dashboard-link";
import { EmptyState } from "@/components/ui/empty-state";
import { getPnrDetail, PnrsServiceError } from "@/services/pnr-service";
import { PnrsErrorPanel } from "@/features/pnrs/pnrs-error-panel";
import {
  ForbiddenState,
  SanitizedErrorState,
  ServiceUnavailableState,
  UnauthorizedState,
} from "@/components/ui/data-source-status";
import { ReadOnlyServiceError } from "@/lib/read-only/read-only-service";

type Props = {
  pnrId: string;
};

export async function PnrDetailPageContent({ pnrId }: Props) {
  try {
    const pnr = await getPnrDetail(pnrId);
    if (!pnr) {
      return (
        <PageContainer>
          <PageHeader title="PNR not found" />
          <EmptyState
            title="PNR or order not found"
            description="The reference may be invalid or you may not have access."
          />
        </PageContainer>
      );
    }

    return (
      <PageContainer data-testid="pnr-detail-page">
        <PreviewModeBadgeSlot />
        <PageHeader
          breadcrumb={
            <Breadcrumb
              items={[
                { label: "Home" },
                { label: "Operations" },
                { label: "PNRs & Orders", href: "/pnrs" },
                { label: pnr.externalReference },
              ]}
            />
          }
          title={pnr.externalReference}
          description={`${pnr.referenceType} · ${pnr.channel}`}
          actions={
            <Link
              href="/pnrs"
              className="inline-flex min-h-11 items-center rounded-xl border border-jp-border bg-white px-3 py-2 text-sm font-medium text-gray-900 hover:bg-gray-50"
            >
              Back to list
            </Link>
          }
        />
        <DataSourceNoticeSlot />
        {pnrHasLinkedBooking(pnr) ? (
          <div className="mb-4 rounded-2xl border border-jp-border bg-slate-50 px-4 py-3 text-sm">
            <p className="text-gray-800">
              This PNR is linked to booking{" "}
              <span className="font-semibold">{pnr.bookingId}</span>. Use booking management for the canonical
              operational workspace.
            </p>
            <Link
              href={pnrViewBookingPath(pnr)}
              className="mt-2 inline-flex min-h-11 items-center rounded-xl bg-jp-accent px-4 py-2 text-sm font-medium text-white"
              data-testid="pnr-open-booking-management"
            >
              Open booking management
            </Link>
          </div>
        ) : null}
        <div className="rounded-2xl border border-jp-border bg-white p-5 shadow-sm">
          <PnrDetailDrawerContent pnr={pnr} />
        </div>
      </PageContainer>
    );
  } catch (error) {
    return (
      <PageContainer>
        <PageHeader title="PNR detail" />
        <PnrDetailError error={error} />
      </PageContainer>
    );
  }
}

function PnrDetailError({ error }: { error: unknown }) {
  if (error instanceof ReadOnlyServiceError) {
    const code = error.envelope.error.code;
    if (code === "unauthenticated") return <UnauthorizedState />;
    if (code === "forbidden") return <ForbiddenState resource="PNR" />;
    if (code === "unavailable") return <ServiceUnavailableState />;
    return (
      <SanitizedErrorState
        message={error.envelope.error.message}
        referenceId={error.envelope.error.referenceIdSafe}
      />
    );
  }
  if (error instanceof PnrsServiceError) {
    return <PnrsErrorPanel referenceId={error.referenceId} message={error.message} />;
  }
  throw error;
}
