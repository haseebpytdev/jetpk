import { AccessRiskBadge, AccessValidationBadge, UserStatusBadge } from "@/components/ui/status-badge";
import { Table, Td, Th } from "@/components/ui/table";
import { formatDate } from "@/lib/format";
import type { RoleSortField, RoleTableRow } from "@/types/roles";

type Props = {
  rows: RoleTableRow[];
  sort: RoleSortField;
  direction: "asc" | "desc";
  onSort: (field: RoleSortField) => void;
  onView: (id: string) => void;
};

const COLUMNS: { key: string; label: string; sortable?: boolean; sortField?: RoleSortField }[] = [
  { key: "id", label: "Role ID" },
  { key: "name", label: "Role name", sortable: true, sortField: "name" },
  { key: "description", label: "Description" },
  { key: "category", label: "Category", sortable: true, sortField: "category" },
  { key: "roleType", label: "System / custom" },
  { key: "protected", label: "Protected" },
  { key: "assignedUserCount", label: "Assigned users", sortable: true, sortField: "assignedUserCount" },
  { key: "permissionCount", label: "Permission count", sortable: true, sortField: "permissionCount" },
  { key: "highRiskPermissionCount", label: "High-risk count", sortable: true, sortField: "highRiskPermissionCount" },
  { key: "scope", label: "Channel scope" },
  { key: "status", label: "Status", sortable: true, sortField: "status" },
  { key: "validationState", label: "Validation", sortable: true, sortField: "validationState" },
  { key: "updatedAt", label: "Last updated", sortable: true, sortField: "updatedAt" },
];

export function RolesDataTable({ rows, sort, direction, onSort, onView }: Props) {
  return (
    <div className="hidden md:block" data-testid="roles-table">
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
              <Td>
                <div className="font-medium">{row.name}</div>
              </Td>
              <Td className="max-w-[14rem] truncate" title={row.description}>{row.description}</Td>
              <Td>{row.categoryLabel}</Td>
              <Td>{row.isSystem ? "System" : "Custom"}</Td>
              <Td>{row.isProtected ? "Yes" : "No"}</Td>
              <Td className="tabular-nums">{row.assignedUserCount}</Td>
              <Td className="tabular-nums">{row.permissionCount}</Td>
              <Td>
                {row.highRiskPermissionCount > 0 ? (
                  <AccessRiskBadge highRisk label={String(row.highRiskPermissionCount)} />
                ) : (
                  <span className="text-xs text-jp-muted">0</span>
                )}
              </Td>
              <Td>{row.scopeLabel}</Td>
              <Td><UserStatusBadge status={row.status} /></Td>
              <Td><AccessValidationBadge status={row.validationState} /></Td>
              <Td>{formatDate(row.updatedAt)}</Td>
            </tr>
          ))}
        </tbody>
      </Table>
    </div>
  );
}
