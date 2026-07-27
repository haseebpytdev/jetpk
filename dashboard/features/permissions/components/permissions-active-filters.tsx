"use client";

import { PERMISSION_GROUP_LABELS } from "@/lib/access-control/permission-catalog";
import type { PermissionsQuery } from "@/types/permissions";

type Props = {
  query: PermissionsQuery;
};

function formatScopeLabel(scope: string): string {
  if (scope.startsWith("channel:")) {
    return scope.replace("channel:", "Channel: ");
  }
  return scope.charAt(0).toUpperCase() + scope.slice(1);
}

function formatActionLabel(action: string): string {
  return action.replace(/([A-Z])/g, " $1").replace(/^./, (c) => c.toUpperCase());
}

const PREREQUISITE_LABELS: Record<PermissionsQuery["prerequisite"], string> = {
  all: "All",
  hasPrerequisite: "Has prerequisite",
  noPrerequisite: "No prerequisite",
  missingPrerequisite: "Missing prerequisite in assignments",
};

const ASSIGNED_STATE_LABELS: Record<PermissionsQuery["assignedState"], string> = {
  all: "All",
  assigned: "Assigned to roles",
  unassigned: "Unassigned",
};

export function PermissionsActiveFilters({ query }: Props) {
  const chips: { label: string; key: string }[] = [];

  if (query.search) chips.push({ label: `Search: ${query.search}`, key: "search" });
  if (query.domain !== "all") chips.push({ label: `Domain: ${PERMISSION_GROUP_LABELS[query.domain]}`, key: "domain" });
  if (query.action !== "all") chips.push({ label: `Action: ${formatActionLabel(query.action)}`, key: "action" });
  if (query.risk !== "all") chips.push({ label: `Risk: ${formatActionLabel(query.risk)}`, key: "risk" });
  if (query.effect !== "all") chips.push({ label: `Effect: ${formatActionLabel(query.effect)}`, key: "effect" });
  if (query.scope !== "all") chips.push({ label: `Scope: ${formatScopeLabel(query.scope)}`, key: "scope" });
  if (query.prerequisite !== "all") chips.push({ label: `Prerequisite: ${PREREQUISITE_LABELS[query.prerequisite]}`, key: "prerequisite" });
  if (query.assignedState !== "all") chips.push({ label: `Assigned: ${ASSIGNED_STATE_LABELS[query.assignedState]}`, key: "assignedState" });
  if (query.validationState !== "all") chips.push({ label: `Validation: ${formatActionLabel(query.validationState)}`, key: "validationState" });

  if (chips.length === 0) return null;

  return (
    <div className="flex flex-wrap gap-2" data-testid="permissions-active-filters" aria-label="Active filters">
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
