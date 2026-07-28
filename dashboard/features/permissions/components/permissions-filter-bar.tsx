"use client";

import { useCallback, useEffect, useState, useTransition } from "react";
import { useDashboardRouter } from "@/lib/dashboard-navigation";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Label } from "@/components/ui/page-layout";
import { Select } from "@/components/ui/select";
import { PERMISSION_GROUP_LABELS } from "@/lib/access-control/permission-catalog";
import { countActivePermissionFilters } from "@/lib/permissions/query-filters";
import { permissionsQueryToSearchParams } from "@/lib/permissions-query";
import type { PermissionsModuleResult, PermissionsQuery } from "@/types/permissions";

type Props = {
  query: PermissionsQuery;
  facets: PermissionsModuleResult["facets"];
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

export function PermissionsFilterBar({ query, facets }: Props) {
  const router = useDashboardRouter();
  const [pending, startTransition] = useTransition();
  const [draft, setDraft] = useState(query);

  useEffect(() => {
    setDraft(query);
  }, [query]);

  const pushQuery = useCallback(
    (next: PermissionsQuery) => {
      const href = `/users/permissions${permissionsQueryToSearchParams(next)}`;
      startTransition(() => router.push(href));
    },
    [router],
  );

  const apply = () => pushQuery({ ...draft, page: 1 });
  const reset = () => {
    const cleared: PermissionsQuery = {
      ...query,
      search: "",
      domain: "all",
      action: "all",
      risk: "all",
      effect: "all",
      scope: "all",
      prerequisite: "all",
      assignedState: "all",
      validationState: "all",
      page: 1,
      sort: "key",
      direction: "asc",
      selected: null,
      state: "",
      previewError: false,
      previewLoading: false,
      previewEmpty: false,
    };
    setDraft(cleared);
    pushQuery(cleared);
  };

  const activeCount = countActivePermissionFilters(query);

  return (
    <Card className="space-y-4" data-testid="permissions-filters">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h2 className="text-sm font-semibold text-gray-900">
          Permission filters{activeCount > 0 ? ` (${activeCount} active)` : ""}
        </h2>
        <div className="flex flex-wrap gap-2">
          <Button variant="ghost" size="sm" type="button" onClick={reset}>
            Reset filters
          </Button>
          <Button size="sm" type="button" onClick={apply} disabled={pending} aria-busy={pending}>
            Apply filters
          </Button>
        </div>
      </div>

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div>
          <Label htmlFor="permissions-search">Search</Label>
          <input
            id="permissions-search"
            type="search"
            className="mt-1 w-full min-h-11 rounded-xl border border-jp-border px-3 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent"
            value={draft.search}
            onChange={(e) => setDraft((d) => ({ ...d, search: e.target.value }))}
            onKeyDown={(e) => {
              if (e.key === "Enter") apply();
            }}
          />
        </div>
        <div>
          <Label htmlFor="permissions-domain">Domain</Label>
          <Select
            id="permissions-domain"
            value={draft.domain}
            onChange={(e) => setDraft((d) => ({ ...d, domain: e.target.value as PermissionsQuery["domain"] }))}
          >
            <option value="all">All domains</option>
            {facets.domains.map((domain) => (
              <option key={domain} value={domain}>{PERMISSION_GROUP_LABELS[domain]}</option>
            ))}
          </Select>
        </div>
        <div>
          <Label htmlFor="permissions-action">Action</Label>
          <Select
            id="permissions-action"
            value={draft.action}
            onChange={(e) => setDraft((d) => ({ ...d, action: e.target.value as PermissionsQuery["action"] }))}
          >
            <option value="all">All actions</option>
            {facets.actions.map((action) => (
              <option key={action} value={action}>{formatActionLabel(action)}</option>
            ))}
          </Select>
        </div>
        <div>
          <Label htmlFor="permissions-risk">Risk</Label>
          <Select
            id="permissions-risk"
            value={draft.risk}
            onChange={(e) => setDraft((d) => ({ ...d, risk: e.target.value as PermissionsQuery["risk"] }))}
          >
            <option value="all">All risk levels</option>
            {facets.risks.map((risk) => (
              <option key={risk} value={risk}>{formatActionLabel(risk)}</option>
            ))}
          </Select>
        </div>
        <div>
          <Label htmlFor="permissions-effect">Effect</Label>
          <Select
            id="permissions-effect"
            value={draft.effect}
            onChange={(e) => setDraft((d) => ({ ...d, effect: e.target.value as PermissionsQuery["effect"] }))}
          >
            <option value="all">All effects</option>
            <option value="allow">Allow</option>
            <option value="deny">Deny</option>
          </Select>
        </div>
        <div>
          <Label htmlFor="permissions-scope">Scope</Label>
          <Select
            id="permissions-scope"
            value={draft.scope}
            onChange={(e) => setDraft((d) => ({ ...d, scope: e.target.value as PermissionsQuery["scope"] }))}
          >
            <option value="all">All scopes</option>
            {facets.scopes.map((scope) => (
              <option key={scope} value={scope}>{formatScopeLabel(scope)}</option>
            ))}
          </Select>
        </div>
        <div>
          <Label htmlFor="permissions-prerequisite">Prerequisite</Label>
          <Select
            id="permissions-prerequisite"
            value={draft.prerequisite}
            onChange={(e) => setDraft((d) => ({ ...d, prerequisite: e.target.value as PermissionsQuery["prerequisite"] }))}
          >
            <option value="all">All prerequisite states</option>
            <option value="hasPrerequisite">Has prerequisite</option>
            <option value="noPrerequisite">No prerequisite</option>
            <option value="missingPrerequisite">Missing prerequisite in assignments</option>
          </Select>
        </div>
        <div>
          <Label htmlFor="permissions-assigned">Assigned state</Label>
          <Select
            id="permissions-assigned"
            value={draft.assignedState}
            onChange={(e) => setDraft((d) => ({ ...d, assignedState: e.target.value as PermissionsQuery["assignedState"] }))}
          >
            <option value="all">All assignment states</option>
            <option value="assigned">Assigned to roles</option>
            <option value="unassigned">Unassigned</option>
          </Select>
        </div>
        <div>
          <Label htmlFor="permissions-validation">Validation state</Label>
          <Select
            id="permissions-validation"
            value={draft.validationState}
            onChange={(e) => setDraft((d) => ({ ...d, validationState: e.target.value as PermissionsQuery["validationState"] }))}
          >
            <option value="all">All validation states</option>
            <option value="valid">Valid</option>
            <option value="warning">Warning</option>
            <option value="blocked">Blocked</option>
            <option value="review">Review</option>
          </Select>
        </div>
      </div>
    </Card>
  );
}
