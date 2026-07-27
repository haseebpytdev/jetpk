import { AccessRiskBadge, AccessValidationBadge } from "@/components/ui/status-badge";
import { Table, Td, Th } from "@/components/ui/table";
import type { PermissionSortField, PermissionTableRow } from "@/types/permissions";

type Props = {
  rows: PermissionTableRow[];
  sort: PermissionSortField;
  direction: "asc" | "desc";
  onSort: (field: PermissionSortField) => void;
  onView: (id: string) => void;
};

const COLUMNS: { key: string; label: string; sortable?: boolean; sortField?: PermissionSortField }[] = [
  { key: "id", label: "Permission ID" },
  { key: "key", label: "Key", sortable: true, sortField: "key" },
  { key: "domain", label: "Domain", sortable: true, sortField: "domain" },
  { key: "action", label: "Action", sortable: true, sortField: "action" },
  { key: "label", label: "Label" },
  { key: "description", label: "Description" },
  { key: "risk", label: "Risk", sortable: true, sortField: "risk" },
  { key: "prerequisite", label: "Prerequisite" },
  { key: "scopes", label: "Supported scopes" },
  { key: "assignedRoles", label: "Assigned roles", sortable: true, sortField: "assignedRoleCount" },
  { key: "validationState", label: "Validation", sortable: true, sortField: "validationState" },
  { key: "laravelPolicyHint", label: "Laravel policy hint" },
];

function formatActionLabel(action: string): string {
  return action.replace(/([A-Z])/g, " $1").replace(/^./, (c) => c.toUpperCase());
}

function formatScopeLabel(scope: string): string {
  if (scope.startsWith("channel:")) {
    return scope.replace("channel:", "");
  }
  return scope;
}

export function PermissionsDataTable({ rows, sort, direction, onSort, onView }: Props) {
  return (
    <div className="hidden md:block" data-testid="permissions-table">
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
              <Td className="font-mono text-xs">{row.key}</Td>
              <Td>{row.domainLabel}</Td>
              <Td>{formatActionLabel(row.action)}</Td>
              <Td>
                <div className="font-medium">{row.label}</div>
              </Td>
              <Td className="max-w-[14rem] text-xs text-jp-muted">{row.description}</Td>
              <Td>
                <div className="flex flex-wrap gap-1">
                  <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs">{formatActionLabel(row.risk)}</span>
                  {row.isHighRisk ? <AccessRiskBadge highRisk /> : null}
                </div>
              </Td>
              <Td className="font-mono text-xs">{row.prerequisiteKey ?? "—"}</Td>
              <Td>
                <div className="flex max-w-[10rem] flex-wrap gap-1">
                  {row.supportedScopes.map((scope) => (
                    <span key={scope} className="rounded-full bg-gray-100 px-2 py-0.5 text-xs">
                      {formatScopeLabel(scope)}
                    </span>
                  ))}
                </div>
              </Td>
              <Td className="tabular-nums">{row.assignedRoleCount}</Td>
              <Td><AccessValidationBadge status={row.validationState} /></Td>
              <Td className="max-w-[12rem] font-mono text-xs text-jp-muted">{row.laravelPolicyHint}</Td>
            </tr>
          ))}
        </tbody>
      </Table>
    </div>
  );
}
