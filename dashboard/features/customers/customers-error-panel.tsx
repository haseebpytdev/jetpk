"use client";

import { useDashboardRouter } from "@/lib/dashboard-navigation";
import { ErrorState } from "@/components/ui/error-state";
import { defaultCustomersQuery, customersQueryToSearchParams } from "@/lib/customers-query";

export function CustomersErrorPanel({
  message,
  referenceId,
}: {
  message: string;
  referenceId: string;
}) {
  const router = useDashboardRouter();

  return (
    <ErrorState
      title="Could not load customers"
      message={message}
      referenceId={referenceId}
      onRetry={() => {
        const q = defaultCustomersQuery();
        router.push(`/customers${customersQueryToSearchParams(q)}`);
      }}
    />
  );
}
