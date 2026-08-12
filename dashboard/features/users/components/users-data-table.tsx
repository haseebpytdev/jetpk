import { UserStatusBadge } from "@/components/ui/status-badge";
import { Table, Td, Th } from "@/components/ui/table";
import type { UserSortField } from "@/types/users";
import type { UserTableRow } from "@/types/users";

type Props = {
  rows: UserTableRow[];
  sort: UserSortField;
  direction: "asc" | "desc";
  page: number;
  pageSize: number;
  onSort: (field: UserSortField) => void;
  onView: (id: string) => void;
};

const COLUMNS: { key: string; label: string; sortable?: boolean; sortField?: UserSortField }[] = [
  { key: "serial", label: "#" },
  { key: "fullName", label: "User", sortable: true, sortField: "fullName" },
  { key: "email", label: "Email", sortable: true, sortField: "email" },
  { key: "userType", label: "User type", sortable: true, sortField: "userType" },
  { key: "jobRole", label: "Job title / Role" },
  { key: "department", label: "Department / Agency", sortable: true, sortField: "department" },
  { key: "status", label: "Status", sortable: true, sortField: "status" },
  { key: "actions", label: "Actions" },
];

function serialLabel(page: number, pageSize: number, index: number): string {
  const n = (page - 1) * pageSize + index + 1;
  return String(n).padStart(2, "0");
}

export function UsersDataTable({ rows, sort, direction, page, pageSize, onSort, onView }: Props) {
  return (
    <div className="hidden md:block" data-testid="users-table">
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
          {rows.map((row, index) => (
            <tr key={row.id}>
              <Td className="tabular-nums text-jp-muted">{serialLabel(page, pageSize, index)}</Td>
              <Td>
                <div className="font-medium">{row.fullName}</div>
                {row.displayName && row.displayName !== row.fullName ? (
                  <div className="text-xs text-jp-muted">{row.displayName}</div>
                ) : null}
              </Td>
              <Td className="max-w-[14rem] break-all">{row.email}</Td>
              <Td>{row.userTypeLabel}</Td>
              <Td>
                <div>{row.jobTitle || "—"}</div>
                <div className="text-xs text-jp-muted">
                  {row.assignedRoleNames.length > 0 ? row.assignedRoleNames.join(", ") : "No roles"}
                </div>
              </Td>
              <Td>{row.department || "—"}</Td>
              <Td>
                <UserStatusBadge status={row.status} />
              </Td>
              <Td>
                <button
                  type="button"
                  className="min-h-11 rounded-xl border border-jp-border px-3 py-2 text-sm font-medium hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent"
                  onClick={() => onView(row.id)}
                  aria-label={`View user ${row.fullName}`}
                >
                  View
                </button>
              </Td>
            </tr>
          ))}
        </tbody>
      </Table>
      <p className="mt-2 text-xs text-jp-muted">
        Internal user ID, MFA, last sign-in, sessions, and validation details remain in View user.
      </p>
    </div>
  );
}
