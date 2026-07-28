"use client";

import { useCallback, useEffect, useState, useTransition } from "react";
import { useDashboardRouter } from "@/lib/dashboard-navigation";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Label } from "@/components/ui/page-layout";
import { Select } from "@/components/ui/select";
import { CATEGORY_LABELS, SCOPE_LABELS, countActiveRoleFilters } from "@/lib/roles/query-filters";
import { rolesQueryToSearchParams } from "@/lib/roles-query";
import type { RolesModuleResult, RolesQuery } from "@/types/roles";

type Props = {
  query: RolesQuery;
  facets: RolesModuleResult["facets"];
};

export function RolesFilterBar({ query, facets }: Props) {
  const router = useDashboardRouter();
  const [pending, startTransition] = useTransition();
  const [draft, setDraft] = useState(query);

  useEffect(() => {
    setDraft(query);
  }, [query]);

  const pushQuery = useCallback(
    (next: RolesQuery, replace = false) => {
      const href = `/users/roles${rolesQueryToSearchParams(next)}`;
      startTransition(() => {
        if (replace) {
          router.replace(href);
        } else {
          router.push(href);
        }
      });
    },
    [router],
  );

  const apply = () => pushQuery({ ...draft, page: 1 });
  const reset = () => {
    const cleared: RolesQuery = {
      ...query,
      search: "",
      category: "all",
      status: "all",
      roleType: "all",
      protected: "all",
      risk: "all",
      validationState: "all",
      channelScope: "all",
      assignedState: "all",
      page: 1,
      sort: "name",
      direction: "asc",
      selected: null,
      compareA: null,
      compareB: null,
      matrixDomain: "",
      matrixRole: "",
      state: "",
      previewError: false,
      previewLoading: false,
      previewEmpty: false,
    };
    setDraft(cleared);
    pushQuery(cleared, true);
  };

  const activeCount = countActiveRoleFilters(query);

  return (
    <Card className="space-y-4" data-testid="roles-filters">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h2 className="text-sm font-semibold text-gray-900">
          Role filters{activeCount > 0 ? ` (${activeCount} active)` : ""}
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
          <Label htmlFor="roles-search">Search</Label>
          <input
            id="roles-search"
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
          <Label htmlFor="roles-category">Category</Label>
          <Select
            id="roles-category"
            value={draft.category}
            onChange={(e) => setDraft((d) => ({ ...d, category: e.target.value as RolesQuery["category"] }))}
          >
            <option value="all">All categories</option>
            {facets.categories.map((c) => (
              <option key={c} value={c}>{CATEGORY_LABELS[c]}</option>
            ))}
          </Select>
        </div>
        <div>
          <Label htmlFor="roles-status">Status</Label>
          <Select
            id="roles-status"
            value={draft.status}
            onChange={(e) => setDraft((d) => ({ ...d, status: e.target.value as RolesQuery["status"] }))}
          >
            <option value="all">All statuses</option>
            {facets.statuses.map((s) => (
              <option key={s} value={s}>{s.replace(/([A-Z])/g, " $1").replace(/^./, (c) => c.toUpperCase())}</option>
            ))}
          </Select>
        </div>
        <div>
          <Label htmlFor="roles-type">System / custom</Label>
          <Select
            id="roles-type"
            value={draft.roleType}
            onChange={(e) => setDraft((d) => ({ ...d, roleType: e.target.value as RolesQuery["roleType"] }))}
          >
            <option value="all">All role types</option>
            <option value="system">System</option>
            <option value="custom">Custom</option>
          </Select>
        </div>
        <div>
          <Label htmlFor="roles-protected">Protected</Label>
          <Select
            id="roles-protected"
            value={draft.protected}
            onChange={(e) => setDraft((d) => ({ ...d, protected: e.target.value as RolesQuery["protected"] }))}
          >
            <option value="all">All protection states</option>
            <option value="protected">Protected</option>
            <option value="unprotected">Unprotected</option>
          </Select>
        </div>
        <div>
          <Label htmlFor="roles-risk">Risk</Label>
          <Select
            id="roles-risk"
            value={draft.risk}
            onChange={(e) => setDraft((d) => ({ ...d, risk: e.target.value as RolesQuery["risk"] }))}
          >
            <option value="all">All risk levels</option>
            <option value="highRisk">High-risk permissions</option>
            <option value="noHighRisk">No high-risk permissions</option>
          </Select>
        </div>
        <div>
          <Label htmlFor="roles-validation">Validation state</Label>
          <Select
            id="roles-validation"
            value={draft.validationState}
            onChange={(e) => setDraft((d) => ({ ...d, validationState: e.target.value as RolesQuery["validationState"] }))}
          >
            <option value="all">All validation states</option>
            <option value="valid">Valid</option>
            <option value="warning">Warning</option>
            <option value="blocked">Blocked</option>
            <option value="review">Review</option>
          </Select>
        </div>
        <div>
          <Label htmlFor="roles-channel-scope">Channel scope</Label>
          <Select
            id="roles-channel-scope"
            value={draft.channelScope}
            onChange={(e) => setDraft((d) => ({ ...d, channelScope: e.target.value as RolesQuery["channelScope"] }))}
          >
            <option value="all">All channel scopes</option>
            {facets.scopes.map((s) => (
              <option key={s} value={s}>{SCOPE_LABELS[s]}</option>
            ))}
          </Select>
        </div>
        <div>
          <Label htmlFor="roles-assigned">Assigned state</Label>
          <Select
            id="roles-assigned"
            value={draft.assignedState}
            onChange={(e) => setDraft((d) => ({ ...d, assignedState: e.target.value as RolesQuery["assignedState"] }))}
          >
            <option value="all">All assignment states</option>
            <option value="assigned">Assigned to users</option>
            <option value="unassigned">Unassigned</option>
            <option value="unused">Unused (active, zero users)</option>
          </Select>
        </div>
      </div>
    </Card>
  );
}
