"use client";

import { USER_STATUS_LABELS, USER_TYPE_LABELS } from "@/types/access-control";
import type { UsersQuery } from "@/types/users";

type Props = {
  query: UsersQuery;
  roleName?: string;
};

export function UsersActiveFilters({ query, roleName }: Props) {
  const chips: { label: string; key: string }[] = [];

  if (query.search) chips.push({ label: `Search: ${query.search}`, key: "search" });
  if (query.status !== "all") chips.push({ label: `Status: ${USER_STATUS_LABELS[query.status]}`, key: "status" });
  if (query.userType !== "all") chips.push({ label: `Type: ${USER_TYPE_LABELS[query.userType]}`, key: "userType" });
  if (query.department) chips.push({ label: `Department: ${query.department}`, key: "department" });
  if (query.role) chips.push({ label: `Role: ${roleName ?? query.role}`, key: "role" });
  if (query.mfa !== "all") chips.push({ label: `MFA: ${query.mfa}`, key: "mfa" });
  if (query.verification !== "all") chips.push({ label: `Verification: ${query.verification}`, key: "verification" });
  if (query.securityState !== "all") chips.push({ label: `Security: ${query.securityState}`, key: "securityState" });
  if (query.validationState !== "all") chips.push({ label: `Validation: ${query.validationState}`, key: "validationState" });

  if (chips.length === 0) return null;

  return (
    <div className="flex flex-wrap gap-2" data-testid="users-active-filters" aria-label="Active filters">
      {chips.map((chip) => (
        <span
          key={chip.key}
          className="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-900 ring-1 ring-inset ring-emerald-600/20"
        >
          {chip.label}
        </span>
      ))}
    </div>
  );
}
