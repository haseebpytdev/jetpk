import { DashboardLink } from "@/components/dashboard/dashboard-link";

export function SupportPaginationNav({
  page,
  pageCount,
  total,
  pageSize = 10,
}: {
  page: number;
  pageCount: number;
  total: number;
  pageSize?: number;
}) {
  const prev = Math.max(1, page - 1);
  const next = Math.min(pageCount, page + 1);

  return (
    <div className="flex flex-wrap items-center justify-between gap-3" data-testid="support-pagination">
      <p className="text-xs text-jp-muted">
        Page {page} of {Math.max(1, pageCount)} · {total} tickets · {pageSize} per page
      </p>
      {pageCount > 1 ? (
        <div className="flex gap-2">
          <DashboardLink
            href={`/support?page=${prev}`}
            className={`min-h-11 rounded-xl border border-jp-border px-3 py-2 text-sm ${page <= 1 ? "pointer-events-none opacity-50" : ""}`}
            aria-disabled={page <= 1}
          >
            Previous
          </DashboardLink>
          <DashboardLink
            href={`/support?page=${next}`}
            className={`min-h-11 rounded-xl border border-jp-border px-3 py-2 text-sm ${page >= pageCount ? "pointer-events-none opacity-50" : ""}`}
            aria-disabled={page >= pageCount}
          >
            Next
          </DashboardLink>
        </div>
      ) : null}
    </div>
  );
}
