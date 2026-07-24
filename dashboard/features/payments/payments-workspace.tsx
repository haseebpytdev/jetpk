"use client";

import { useCallback, useEffect, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Drawer } from "@/components/ui/drawer";
import { EmptyState } from "@/components/ui/empty-state";
import { Pagination } from "@/components/ui/pagination";
import { PaymentDetailDrawerContent } from "@/features/payments/payment-detail-drawer";
import { PaymentsFilters } from "@/features/payments/payments-filters";
import { PaymentsMobileCards } from "@/features/payments/payments-mobile-cards";
import { PaymentsSummary } from "@/features/payments/payments-summary";
import { PaymentsTable } from "@/features/payments/payments-table";
import { paymentsQueryToSearchParams } from "@/lib/payments-query";
import type {
  PaymentSortField,
  PaymentsPageResult,
  PaymentsQuery,
  TransactionRecord,
} from "@/types/payment";

type Props = {
  query: PaymentsQuery;
  result: PaymentsPageResult;
  selectedTransaction: TransactionRecord | null;
};

export function PaymentsWorkspace({ query, result, selectedTransaction }: Props) {
  const router = useRouter();
  const [, startTransition] = useTransition();
  const [drawerDismissed, setDrawerDismissed] = useState(false);

  useEffect(() => {
    setDrawerDismissed(false);
  }, [query.selectedTransactionId]);

  const pushQuery = useCallback(
    (overrides: Partial<PaymentsQuery>) => {
      const next = { ...query, ...overrides };
      startTransition(() => {
        router.push(`/payments${paymentsQueryToSearchParams(next)}`);
      });
    },
    [query, router],
  );

  const onSort = (field: PaymentSortField) => {
    const direction =
      query.sort === field && query.direction === "desc" ? "asc" : query.sort === field ? "desc" : "desc";
    pushQuery({ sort: field, direction, page: 1 });
  };

  const onView = (transactionId: string) => {
    pushQuery({ selectedTransactionId: transactionId });
  };

  const onCloseDrawer = useCallback(() => {
    setDrawerDismissed(true);
    pushQuery({ selectedTransactionId: null });
  }, [pushQuery]);

  const drawerOpen =
    !drawerDismissed && Boolean(query.selectedTransactionId && selectedTransaction);

  const empty = result.total === 0;

  return (
    <>
      <PaymentsSummary summary={result.summary} />
      <PaymentsFilters query={query} facets={result.facets} />

      {empty ? (
        <EmptyState
          title="No transactions match your filters"
          description="Try clearing filters or broadening your search. All data shown is synthetic preview data."
        />
      ) : (
        <>
          <PaymentsTable
            transactions={result.transactions}
            query={query}
            onSort={onSort}
            onView={onView}
          />
          <PaymentsMobileCards transactions={result.transactions} onView={onView} />
          <Pagination
            page={result.page}
            pageCount={result.pageCount}
            pageSize={result.pageSize}
            total={result.total}
            onPageChange={(page) => pushQuery({ page })}
            onPageSizeChange={(pageSize) => pushQuery({ pageSize, page: 1 })}
            ariaLabel="Payments pagination"
          />
        </>
      )}

      <Drawer
        open={drawerOpen}
        onClose={onCloseDrawer}
        title={selectedTransaction ? selectedTransaction.transactionId : "Transaction details"}
        description={
          selectedTransaction
            ? `${selectedTransaction.paymentId} · ${selectedTransaction.bookingId}`
            : undefined
        }
        closeAriaLabel="Close payment details"
      >
        {selectedTransaction ? <PaymentDetailDrawerContent transaction={selectedTransaction} /> : null}
      </Drawer>
    </>
  );
}
