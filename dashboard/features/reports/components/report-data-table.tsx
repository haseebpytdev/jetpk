import { DashboardLink as Link } from "@/components/dashboard/dashboard-link";
import { Card, CardDescription, CardTitle } from "@/components/ui/card";
import { Table, Td, Th } from "@/components/ui/table";
import type { ReportModuleTable } from "@/types/report";

type Props = {
  table: ReportModuleTable;
  onSort?: (key: string) => void;
  sort?: string;
  direction?: "asc" | "desc";
  mobileTitle?: string;
};

export function ReportDataTable({ table, onSort, sort, direction, mobileTitle = "Report rows" }: Props) {
  if (table.columns.length === 0) return null;

  return (
    <>
      <div className="hidden md:block" data-testid="reports-table">
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
            {table.rows.map((row, index) => (
              <tr key={String(row.id ?? row.transactionId ?? row.reference ?? index)}>
                {table.columns.map((col) => (
                  <Td key={col.key} className={col.align === "end" ? "text-right" : undefined}>
                    {col.key === "id" || col.key === "transactionId" || col.key === "reference" ? (
                      row.href ? (
                        <Link href={String(row.href)} className="font-medium text-jp-accent hover:underline">
                          {row[col.key]}
                        </Link>
                      ) : (
                        row[col.key]
                      )
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

      <div className="space-y-3 md:hidden" data-testid="reports-mobile-cards">
        {table.rows.map((row, index) => (
          <Card key={String(row.id ?? row.transactionId ?? row.reference ?? index)} className="p-4">
            <CardTitle className="text-base">{mobileTitle}</CardTitle>
            <CardDescription className="mt-2 space-y-1">
              {table.columns.map((col) => (
                <div key={col.key} className="flex justify-between gap-3 text-sm">
                  <span className="text-jp-muted">{col.label}</span>
                  <span className="text-right">{row[col.key]}</span>
                </div>
              ))}
            </CardDescription>
            {row.href ? (
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
