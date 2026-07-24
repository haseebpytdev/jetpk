"use client";

import { useCallback, useEffect, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Drawer } from "@/components/ui/drawer";
import { EmptyState } from "@/components/ui/empty-state";
import { Pagination } from "@/components/ui/pagination";
import { AgentDetailDrawerContent } from "@/features/agents/agent-detail-drawer";
import { AgentsFilters } from "@/features/agents/agents-filters";
import { AgentsMobileCards } from "@/features/agents/agents-mobile-cards";
import { AgentsSummary } from "@/features/agents/agents-summary";
import { AgentsTable } from "@/features/agents/agents-table";
import { agentsQueryToSearchParams } from "@/lib/agents-query";
import type { AgentRecord, AgentSortField, AgentsPageResult, AgentsQuery } from "@/types/agent";

type Props = {
  query: AgentsQuery;
  result: AgentsPageResult;
  selectedAgent: AgentRecord | null;
};

export function AgentsWorkspace({ query, result, selectedAgent }: Props) {
  const router = useRouter();
  const [, startTransition] = useTransition();
  const [drawerDismissed, setDrawerDismissed] = useState(false);

  useEffect(() => {
    setDrawerDismissed(false);
  }, [query.selectedId]);

  const pushQuery = useCallback(
    (overrides: Partial<AgentsQuery>) => {
      const next = { ...query, ...overrides };
      startTransition(() => {
        router.push(`/agents${agentsQueryToSearchParams(next)}`);
      });
    },
    [query, router],
  );

  const onSort = (field: AgentSortField) => {
    const direction =
      query.sort === field && query.direction === "desc" ? "asc" : query.sort === field ? "desc" : "desc";
    pushQuery({ sort: field, direction, page: 1 });
  };

  const onView = (id: string) => {
    pushQuery({ selectedId: id });
  };

  const onCloseDrawer = useCallback(() => {
    setDrawerDismissed(true);
    pushQuery({ selectedId: null });
  }, [pushQuery]);

  const drawerOpen = !drawerDismissed && Boolean(query.selectedId && selectedAgent);
  const empty = result.total === 0;

  return (
    <>
      <AgentsSummary summary={result.summary} />
      <AgentsFilters query={query} facets={result.facets} />

      {empty ? (
        <EmptyState
          title="No agents match your filters"
          description="Try clearing filters or broadening your search. All data shown is synthetic preview data."
        />
      ) : (
        <>
          <AgentsTable agents={result.agents} query={query} onSort={onSort} onView={onView} />
          <AgentsMobileCards agents={result.agents} onView={onView} />
          <Pagination
            page={result.page}
            pageCount={result.pageCount}
            pageSize={result.pageSize}
            total={result.total}
            onPageChange={(page) => pushQuery({ page })}
            onPageSizeChange={(pageSize) => pushQuery({ pageSize, page: 1 })}
            ariaLabel="Agents pagination"
          />
        </>
      )}

      <Drawer
        open={drawerOpen}
        onClose={onCloseDrawer}
        title={selectedAgent ? selectedAgent.agencyName : "Agent details"}
        description={
          selectedAgent ? `${selectedAgent.id} · ${selectedAgent.tradingName}` : undefined
        }
        closeAriaLabel="Close agent details"
      >
        {selectedAgent ? <AgentDetailDrawerContent agent={selectedAgent} /> : null}
      </Drawer>
    </>
  );
}
