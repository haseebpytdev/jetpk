import { AccessValidationBadge, MfaStatusBadge, UserStatusBadge } from "@/components/ui/status-badge";
import { Table, Td, Th } from "@/components/ui/table";
import { formatDate } from "@/lib/format";
import type { UserSortField } from "@/types/users";
import type { UserTableRow } from "@/types/users";

type Props = {
  rows: UserTableRow[];
  sort: UserSortField;
  direction: "asc" | "desc";
  onSort: (field: UserSortField) => void;
  onView: (id: string) => void;
};

const COLUMNS: { key: string; label: string; sortable?: boolean; sortField?: UserSortField }[] = [
  { key: "id", label: "User ID" },
  { key: "fullName", label: "User", sortable: true, sortField: "fullName" },
  { key: "email", label: "Email", sortable: true, sortField: "email" },
  { key: "department", label: "Department", sortable: true, sortField: "department" },
  { key: "jobTitle", label: "Job title" },
  { key: "userType", label: "User type", sortable: true, sortField: "userType" },
  { key: "roles", label: "Assigned roles" },
  { key: "status", label: "Status", sortable: true, sortField: "status" },
  { key: "mfa", label: "MFA" },
  { key: "lastSignIn", label: "Last sign-in", sortable: true, sortField: "lastSignIn" },
  { key: "sessions", label: "Active sessions" },
  { key: "validationState", label: "Validation", sortable: true, sortField: "validationState" },
];

export function UsersDataTable({ rows, sort, direction, onSort, onView }: Props) {
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
                <div className="font-medium">{row.fullName}</div>
                <div className="text-xs text-jp-muted">{row.displayName}</div>
              </Td>
              <Td className="max-w-[12rem] break-all">{row.email}</Td>
              <Td>{row.department}</Td>
              <Td>{row.jobTitle}</Td>
              <Td>{row.userTypeLabel}</Td>
              <Td>
                <div className="flex flex-wrap gap-1">
                  {row.assignedRoleNames.length > 0 ? (
                    row.assignedRoleNames.map((name) => (
                      <span key={name} className="rounded-full bg-gray-100 px-2 py-0.5 text-xs">{name}</span>
                    ))
                  ) : (
                    <span className="text-xs text-jp-muted">No roles</span>
                  )}
                </div>
              </Td>
              <Td><UserStatusBadge status={row.status} /></Td>
              <Td><MfaStatusBadge status={row.mfaState} /></Td>
              <Td>{row.lastSignInAt ? formatDate(row.lastSignInAt) : "—"}</Td>
              <Td className="tabular-nums">{row.activeSessionCount}</Td>
              <Td><AccessValidationBadge status={row.validationState} /></Td>
            </tr>
          ))}
        </tbody>
      </Table>
    </div>
  );
}
