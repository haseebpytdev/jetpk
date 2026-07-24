"use client";

import { useRouter } from "next/navigation";
import { ErrorState } from "@/components/ui/error-state";
import { defaultTicketsQuery, ticketsQueryToSearchParams } from "@/lib/tickets-query";

export function TicketsErrorPanel({
  message,
  referenceId,
}: {
  message: string;
  referenceId: string;
}) {
  const router = useRouter();

  return (
    <ErrorState
      title="Could not load tickets"
      message={message}
      referenceId={referenceId}
      onRetry={() => {
        const q = defaultTicketsQuery();
        router.push(`/tickets${ticketsQueryToSearchParams(q)}`);
      }}
    />
  );
}
