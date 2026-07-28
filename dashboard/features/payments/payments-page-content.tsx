import { PaymentsWorkspace } from "@/features/payments/payments-workspace";
import { Breadcrumb, PageContainer, PageHeader } from "@/components/ui/page-layout";
import { DataSourceNoticeSlot, PreviewModeBadgeSlot } from "@/components/dashboard/data-source-notice";
import { parsePaymentsQuery } from "@/lib/payments-query";
import { PaymentsServiceError, getPaymentsPage, getTransactionDetail } from "@/services/payment-service";
import { PaymentsErrorPanel } from "@/features/payments/payments-error-panel";
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

export async function PaymentsPageContent({ searchParams }: Props) {
  const sp = await searchParams;
  const query = parsePaymentsQuery(sp);

  try {
    const result = await getPaymentsPage(query);
    const selectedTransaction = query.selectedTransactionId
      ? await getTransactionDetail(query.selectedTransactionId)
      : null;

    return (
      <PageContainer>
        <PreviewModeBadgeSlot />
        <PageHeader
          breadcrumb={
            <Breadcrumb items={[{ label: "Home" }, { label: "Operations" }, { label: "Payments" }]} />
          }
          title="Payments"
          description="Financial ledger with filters, sorting, and read-only transaction detail."
        />
        <DataSourceNoticeSlot />
        <PaymentsWorkspace query={query} result={result} selectedTransaction={selectedTransaction} />
      </PageContainer>
    );
  } catch (e) {
    return (
      <PageContainer>
        <PageHeader title="Payments" />
        <PaymentsModuleError error={e} />
      </PageContainer>
    );
  }
}

function PaymentsModuleError({ error }: { error: unknown }) {
  if (error instanceof ReadOnlyServiceError) {
    const code = error.envelope.error.code;
    if (code === "unauthenticated") return <UnauthorizedState />;
    if (code === "forbidden") return <ForbiddenState resource="payments" />;
    if (code === "unavailable") return <ServiceUnavailableState />;
    return (
      <SanitizedErrorState
        message={error.envelope.error.message}
        referenceId={error.envelope.error.referenceIdSafe}
      />
    );
  }
  if (error instanceof PaymentsServiceError) {
    return <PaymentsErrorPanel referenceId={error.referenceId} message={error.message} />;
  }
  throw error;
}
