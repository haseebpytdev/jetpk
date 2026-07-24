"use client";

import { useCallback, useEffect, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Drawer } from "@/components/ui/drawer";
import { EmptyState } from "@/components/ui/empty-state";
import { Pagination } from "@/components/ui/pagination";
import { TicketDetailDrawerContent } from "@/features/tickets/ticket-detail-drawer";
import { TicketsFilters } from "@/features/tickets/tickets-filters";
import { TicketsMobileCards } from "@/features/tickets/tickets-mobile-cards";
import { TicketsSummary } from "@/features/tickets/tickets-summary";
import { TicketsTable } from "@/features/tickets/tickets-table";
import { ticketsQueryToSearchParams } from "@/lib/tickets-query";
import type { TicketRecord, TicketSortField, TicketsPageResult, TicketsQuery } from "@/types/ticket";

type Props = {
  query: TicketsQuery;
  result: TicketsPageResult;
  selectedTicket: TicketRecord | null;
};

export function TicketsWorkspace({ query, result, selectedTicket }: Props) {
  const router = useRouter();
  const [, startTransition] = useTransition();
  const [drawerDismissed, setDrawerDismissed] = useState(false);

  useEffect(() => {
    setDrawerDismissed(false);
  }, [query.selectedId]);

  const pushQuery = useCallback(
    (overrides: Partial<TicketsQuery>) => {
      const next = { ...query, ...overrides };
      startTransition(() => {
        router.push(`/tickets${ticketsQueryToSearchParams(next)}`);
      });
    },
    [query, router],
  );

  const onSort = (field: TicketSortField) => {
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

  const drawerOpen = !drawerDismissed && Boolean(query.selectedId && selectedTicket);
  const empty = result.total === 0;

  return (
    <>
      <TicketsSummary summary={result.summary} />
      <TicketsFilters query={query} facets={result.facets} />

      {empty ? (
        <EmptyState
          title="No tickets or documents match your filters"
          description="Try clearing filters or broadening your search. All data shown is synthetic preview data."
        />
      ) : (
        <>
          <TicketsTable tickets={result.tickets} query={query} onSort={onSort} onView={onView} />
          <TicketsMobileCards tickets={result.tickets} onView={onView} />
          <Pagination
            page={result.page}
            pageCount={result.pageCount}
            pageSize={result.pageSize}
            total={result.total}
            onPageChange={(page) => pushQuery({ page })}
            onPageSizeChange={(pageSize) => pushQuery({ pageSize, page: 1 })}
            ariaLabel="Tickets pagination"
          />
        </>
      )}

      <Drawer
        open={drawerOpen}
        onClose={onCloseDrawer}
        title={selectedTicket ? selectedTicket.documentType : "Ticket details"}
        description={
          selectedTicket
            ? `${selectedTicket.id} · ${selectedTicket.maskedExternalId}`
            : undefined
        }
        closeAriaLabel="Close ticket details"
      >
        {selectedTicket ? <TicketDetailDrawerContent ticket={selectedTicket} /> : null}
      </Drawer>
    </>
  );
}
