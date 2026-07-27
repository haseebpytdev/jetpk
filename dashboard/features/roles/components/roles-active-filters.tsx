"use client";

import { CATEGORY_LABELS, SCOPE_LABELS } from "@/lib/roles/query-filters";
import type { RolesQuery } from "@/types/roles";

type Props = {
  query: RolesQuery;
};

export function RolesActiveFilters({ query }: Props) {
  const chips: { label: string; key: string }[] = [];

  if (query.search) chips.push({ label: `Search: ${query.search}`, key: "search" });
  if (query.category !== "all") chips.push({ label: `Category: ${CATEGORY_LABELS[query.category]}`, key: "category" });
  if (query.status !== "all") chips.push({ label: `Status: ${query.status}`, key: "status" });
  if (query.roleType !== "all") chips.push({ label: `Type: ${query.roleType}`, key: "roleType" });
  if (query.protected !== "all") chips.push({ label: `Protected: ${query.protected}`, key: "protected" });
  if (query.risk !== "all") chips.push({ label: `Risk: ${query.risk}`, key: "risk" });
  if (query.validationState !== "all") chips.push({ label: `Validation: ${query.validationState}`, key: "validationState" });
  if (query.channelScope !== "all") chips.push({ label: `Scope: ${SCOPE_LABELS[query.channelScope]}`, key: "channelScope" });
  if (query.assignedState !== "all") chips.push({ label: `Assigned: ${query.assignedState}`, key: "assignedState" });

  if (chips.length === 0) return null;

  return (
    <div className="flex flex-wrap gap-2" data-testid="roles-active-filters" aria-label="Active filters">
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
