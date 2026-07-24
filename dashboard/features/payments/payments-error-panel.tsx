"use client";

import { useRouter } from "next/navigation";
import { ErrorState } from "@/components/ui/error-state";
import { defaultPaymentsQuery, paymentsQueryToSearchParams } from "@/lib/payments-query";

export function PaymentsErrorPanel({
  message,
  referenceId,
}: {
  message: string;
  referenceId: string;
}) {
  const router = useRouter();

  return (
    <ErrorState
      title="Could not load payments"
      message={message}
      referenceId={referenceId}
      onRetry={() => {
        const q = defaultPaymentsQuery();
        router.push(`/payments${paymentsQueryToSearchParams(q)}`);
      }}
    />
  );
}
