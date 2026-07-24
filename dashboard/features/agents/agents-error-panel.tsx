"use client";

import { useRouter } from "next/navigation";
import { ErrorState } from "@/components/ui/error-state";
import { agentsQueryToSearchParams, defaultAgentsQuery } from "@/lib/agents-query";

export function AgentsErrorPanel({
  message,
  referenceId,
}: {
  message: string;
  referenceId: string;
}) {
  const router = useRouter();

  return (
    <ErrorState
      title="Could not load agents"
      message={message}
      referenceId={referenceId}
      onRetry={() => {
        const q = defaultAgentsQuery();
        router.push(`/agents${agentsQueryToSearchParams(q)}`);
      }}
    />
  );
}
