"use client";

import { useCallback, useEffect, useState, useTransition } from "react";
import { useDashboardRouter } from "@/lib/dashboard-navigation";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Label } from "@/components/ui/page-layout";
import { Select } from "@/components/ui/select";
import { countActiveUserFilters } from "@/lib/users/query-filters";
import { usersQueryToSearchParams } from "@/lib/users-query";
import { USER_STATUS_LABELS, USER_TYPE_LABELS } from "@/types/access-control";
import type { UsersModuleResult, UsersQuery } from "@/types/users";

type Props = {
  query: UsersQuery;
  facets: UsersModuleResult["facets"];
};

export function UsersFilterBar({ query, facets }: Props) {
  const router = useDashboardRouter();
  const [pending, startTransition] = useTransition();
  const [draft, setDraft] = useState(query);

  useEffect(() => {
    setDraft(query);
  }, [query]);

  const pushQuery = useCallback(
    (next: UsersQuery) => {
      const href = `/users${usersQueryToSearchParams(next)}`;
      startTransition(() => router.push(href));
    },
    [router],
  );

  const apply = () => pushQuery({ ...draft, page: 1 });
  const reset = () => {
    const cleared: UsersQuery = {
      ...query,
      search: "",
      status: "all",
      userType: "all",
      department: "",
      role: "",
      mfa: "all",
      verification: "all",
      securityState: "all",
      validationState: "all",
      page: 1,
      sort: "fullName",
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

  const activeCount = countActiveUserFilters(query);

  return (
    <Card className="space-y-4" data-testid="users-filters">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h2 className="text-sm font-semibold text-gray-900">
          User filters{activeCount > 0 ? ` (${activeCount} active)` : ""}
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
          <Label htmlFor="users-search">Search</Label>
          <input
            id="users-search"
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
          <Label htmlFor="users-status">Status</Label>
          <Select
            id="users-status"
            value={draft.status}
            onChange={(e) => setDraft((d) => ({ ...d, status: e.target.value as UsersQuery["status"] }))}
          >
            <option value="all">All statuses</option>
            {facets.statuses.map((s) => (
              <option key={s} value={s}>{USER_STATUS_LABELS[s]}</option>
            ))}
          </Select>
        </div>
        <div>
          <Label htmlFor="users-type">User type</Label>
          <Select
            id="users-type"
            value={draft.userType}
            onChange={(e) => setDraft((d) => ({ ...d, userType: e.target.value as UsersQuery["userType"] }))}
          >
            <option value="all">All types</option>
            {facets.userTypes.map((t) => (
              <option key={t} value={t}>{USER_TYPE_LABELS[t]}</option>
            ))}
          </Select>
        </div>
        <div>
          <Label htmlFor="users-department">Department</Label>
          <Select
            id="users-department"
            value={draft.department}
            onChange={(e) => setDraft((d) => ({ ...d, department: e.target.value }))}
          >
            <option value="">All departments</option>
            {facets.departments.map((d) => (
              <option key={d} value={d}>{d}</option>
            ))}
          </Select>
        </div>
        <div>
          <Label htmlFor="users-role">Role</Label>
          <Select
            id="users-role"
            value={draft.role}
            onChange={(e) => setDraft((d) => ({ ...d, role: e.target.value }))}
          >
            <option value="">All roles</option>
            {facets.roles.map((r) => (
              <option key={r.id} value={r.id}>{r.name}</option>
            ))}
          </Select>
        </div>
        <div>
          <Label htmlFor="users-mfa">MFA state</Label>
          <Select
            id="users-mfa"
            value={draft.mfa}
            onChange={(e) => setDraft((d) => ({ ...d, mfa: e.target.value as UsersQuery["mfa"] }))}
          >
            <option value="all">All MFA states</option>
            <option value="enabled">Enabled</option>
            <option value="disabled">Disabled</option>
            <option value="required">Required</option>
            <option value="pendingSetup">Pending setup</option>
          </Select>
        </div>
        <div>
          <Label htmlFor="users-verification">Verification</Label>
          <Select
            id="users-verification"
            value={draft.verification}
            onChange={(e) => setDraft((d) => ({ ...d, verification: e.target.value as UsersQuery["verification"] }))}
          >
            <option value="all">All verification states</option>
            <option value="verified">Verified</option>
            <option value="pending">Pending</option>
            <option value="unverified">Unverified</option>
            <option value="expired">Expired</option>
          </Select>
        </div>
        <div>
          <Label htmlFor="users-security">Security state</Label>
          <Select
            id="users-security"
            value={draft.securityState}
            onChange={(e) => setDraft((d) => ({ ...d, securityState: e.target.value as UsersQuery["securityState"] }))}
          >
            <option value="all">All security states</option>
            <option value="normal">Normal</option>
            <option value="warning">Warning</option>
            <option value="locked">Locked</option>
            <option value="suspended">Suspended</option>
            <option value="reviewRequired">Review required</option>
            <option value="staleInvitation">Stale invitation</option>
          </Select>
        </div>
        <div>
          <Label htmlFor="users-validation">Validation state</Label>
          <Select
            id="users-validation"
            value={draft.validationState}
            onChange={(e) => setDraft((d) => ({ ...d, validationState: e.target.value as UsersQuery["validationState"] }))}
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
