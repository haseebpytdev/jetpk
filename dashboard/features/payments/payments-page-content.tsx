import { PaymentsWorkspace } from "@/features/payments/payments-workspace";
import { Breadcrumb, PageContainer, PageHeader, PreviewDataBanner } from "@/components/ui/page-layout";
import { parsePaymentsQuery } from "@/lib/payments-query";
import { PaymentsServiceError, getPaymentsPage, getTransactionDetail } from "@/services/payment-service";
import { PaymentsErrorPanel } from "@/features/payments/payments-error-panel";

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
        <PageHeader
          breadcrumb={
            <Breadcrumb items={[{ label: "Home" }, { label: "Operations" }, { label: "Payments" }]} />
          }
          title="Payments"
          description="Financial ledger — mock preview data with filters, sorting, and read-only transaction detail."
        />
        <PreviewDataBanner />
        <PaymentsWorkspace query={query} result={result} selectedTransaction={selectedTransaction} />
      </PageContainer>
    );
  } catch (e) {
    if (e instanceof PaymentsServiceError) {
      return (
        <PageContainer>
          <PageHeader title="Payments" />
          <PaymentsErrorPanel referenceId={e.referenceId} message={e.message} />
        </PageContainer>
      );
    }
    throw e;
  }
}
