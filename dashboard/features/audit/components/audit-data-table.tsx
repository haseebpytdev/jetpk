import { AccessRiskBadge, AccessValidationBadge } from "@/components/ui/status-badge";
import { Table, Td, Th } from "@/components/ui/table";
import { formatDate } from "@/lib/format";
import type { AuditSortField, AuditTableRow } from "@/types/audit";
import { AuditOutcomeBadge, AuditSeverityBadge } from "@/features/audit/components/audit-status-badges";

type Props = {
  rows: AuditTableRow[];
  sort: AuditSortField;
  direction: "asc" | "desc";
  onSort: (field: AuditSortField) => void;
  onView: (id: string) => void;
};

const COLUMNS: { key: string; label: string; sortable?: boolean; sortField?: AuditSortField }[] = [
  { key: "id", label: "Event ID", sortable: true, sortField: "id" },
  { key: "occurredAt", label: "Timestamp", sortable: true, sortField: "occurredAt" },
  { key: "category", label: "Category", sortable: true, sortField: "category" },
  { key: "event", label: "Event" },
  { key: "actor", label: "Actor" },
  { key: "target", label: "Target" },
  { key: "sourceModule", label: "Source module", sortable: true, sortField: "sourceModule" },
  { key: "severity", label: "Severity", sortable: true, sortField: "severity" },
  { key: "outcome", label: "Outcome", sortable: true, sortField: "outcome" },
  { key: "risk", label: "Risk", sortable: true, sortField: "riskState" },
  { key: "channel", label: "Channel" },
  { key: "validation", label: "Validation", sortable: true, sortField: "validationState" },
];

export function AuditDataTable({ rows, sort, direction, onSort, onView }: Props) {
  return (
    <div className="hidden md:block" data-testid="audit-table">
      <Table>
        <thead>
          <tr>
            {COLUMNS.map((col) => (
              <Th key={col.key}>
                {col.sortable && col.sortField ? (
                  <button
                    type="button"
                    className="font-semibold hover:text-jp-accent focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent"
                    onClick={() => onSort(col.sortField!)}
                    aria-sort={sort === col.sortField ? (direction === "asc" ? "ascending" : "descending") : "none"}
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
          {rows.map((row) => (
            <tr key={row.id}>
              <Td>
                <button
                  type="button"
                  className="font-medium text-jp-accent hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent"
                  onClick={() => onView(row.id)}
                  aria-label={row.id}
                >
                  {row.id}
                </button>
              </Td>
              <Td className="whitespace-nowrap text-xs">{formatDate(row.occurredAt)}</Td>
              <Td>{row.category}</Td>
              <Td>
                <div className="max-w-[14rem] font-medium">{row.eventLabel}</div>
                <div className="text-xs text-jp-muted">{row.type}</div>
              </Td>
              <Td>
                <div>{row.actorName}</div>
                <div className="text-xs text-jp-muted">{row.actorType}</div>
              </Td>
              <Td>
                <div className="max-w-[12rem] truncate">{row.targetLabel}</div>
                <div className="text-xs text-jp-muted">{row.targetType}</div>
              </Td>
              <Td>{row.sourceModule}</Td>
              <Td><AuditSeverityBadge severity={row.severity} /></Td>
              <Td><AuditOutcomeBadge outcome={row.outcome} /></Td>
              <Td><AccessRiskBadge highRisk={row.riskState === "high" || row.riskState === "critical"} label={row.riskState} /></Td>
              <Td>{row.channel ?? "—"}</Td>
              <Td><AccessValidationBadge status={row.validationState} /></Td>
            </tr>
          ))}
        </tbody>
      </Table>
    </div>
  );
}
