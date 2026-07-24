"use client";

import { useRouter } from "next/navigation";
import { ErrorState } from "@/components/ui/error-state";
import { defaultSuppliersQuery, suppliersQueryToSearchParams } from "@/lib/suppliers-query";

export function SuppliersErrorPanel({
  message,
  referenceId,
}: {
  message: string;
  referenceId: string;
}) {
  const router = useRouter();

  return (
    <ErrorState
      title="Could not load suppliers"
      message={message}
      referenceId={referenceId}
      onRetry={() => {
        const q = defaultSuppliersQuery();
        router.push(`/suppliers${suppliersQueryToSearchParams(q)}`);
      }}
    />
  );
}
