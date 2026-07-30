import { DashboardLink as Link } from "@/components/dashboard/dashboard-link";
import { Card, CardTitle } from "@/components/ui/card";
import { Table, Td, Th } from "@/components/ui/table";
import type { CmsModuleTable } from "@/types/cms";

type Props = {
  table: CmsModuleTable;
  onSort?: (key: string) => void;
  sort?: string;
  direction?: "asc" | "desc";
  onView?: (id: string) => void;
  mobileTitle?: string;
};

export function CmsDataTable({ table, onSort, sort, direction, onView, mobileTitle = "CMS record" }: Props) {
  if (table.columns.length === 0) return null;

  return (
    <>
      <div className="hidden md:block" data-testid="cms-table">
        <Table>
          <thead>
            <tr>
              {table.columns.map((col) => (
                <Th key={col.key} className={col.align === "end" ? "text-right" : undefined}>
                  {col.sortable && onSort ? (
                    <button
                      type="button"
                      className="font-semibold hover:text-jp-accent focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent"
                      onClick={() => onSort(col.key)}
                      aria-sort={sort === col.key ? (direction === "asc" ? "ascending" : "descending") : "none"}
                    >
                      {col.label}
                    </button>
                  ) : (
                    col.label
                  )}
                </Th>
              ))}
            </tr>
          </thead>
          <tbody>
            {table.rows.map((row) => (
              <tr key={row.id}>
                {table.columns.map((col) => (
                  <Td key={col.key} className={col.align === "end" ? "text-right" : undefined}>
                    {col.key === "id" && onView ? (
                      <button
                        type="button"
                        className="font-medium text-jp-accent hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent"
                        onClick={() => onView(String(row.id))}
                      >
                        {row[col.key]}
                      </button>
                    ) : col.key === "componentKey" ? (
                      <code className="break-all text-xs">{row[col.key]}</code>
                    ) : (
                      row[col.key]
                    )}
                  </Td>
                ))}
              </tr>
            ))}
          </tbody>
        </Table>
      </div>

      <div className="space-y-3 md:hidden" data-testid="cms-mobile-cards">
        {table.rows.map((row) => (
          <Card key={row.id} className="p-4">
            <CardTitle className="text-base">{mobileTitle}</CardTitle>
            <div className="mt-2 space-y-1 text-sm text-jp-muted">
              {table.columns.map((col) => (
                <div key={col.key} className="flex justify-between gap-3 text-sm">
                  <span className="text-jp-muted">{col.label}</span>
                  <span className="max-w-[60%] break-words text-right text-gray-900">{row[col.key]}</span>
                </div>
              ))}
            </div>
            {onView ? (
              <button
                type="button"
                className="mt-3 text-sm font-medium text-jp-accent hover:underline"
                onClick={() => onView(String(row.id))}
              >
                View record
              </button>
            ) : row.href ? (
              <Link href={String(row.href)} className="mt-3 inline-block text-sm font-medium text-jp-accent hover:underline">
                View record
              </Link>
            ) : null}
          </Card>
        ))}
      </div>
    </>
  );
}
