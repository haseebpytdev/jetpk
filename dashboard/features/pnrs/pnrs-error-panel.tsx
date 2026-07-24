"use client";

import { useRouter } from "next/navigation";
import { ErrorState } from "@/components/ui/error-state";
import { defaultPnrsQuery, pnrsQueryToSearchParams } from "@/lib/pnrs-query";

export function PnrsErrorPanel({
  message,
  referenceId,
}: {
  message: string;
  referenceId: string;
}) {
  const router = useRouter();

  return (
    <ErrorState
      title="Could not load PNRs and orders"
      message={message}
      referenceId={referenceId}
      onRetry={() => {
        const q = defaultPnrsQuery();
        router.push(`/pnrs${pnrsQueryToSearchParams(q)}`);
      }}
    />
  );
}
