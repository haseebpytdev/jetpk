"use client";

import { useCallback, useEffect, useState, useTransition } from "react";
import { useDashboardRouter } from "@/lib/dashboard-navigation";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/page-layout";
import { Select } from "@/components/ui/select";
import { countActiveUserFilters } from "@/lib/users/query-filters";
import { usersQueryToSearchParams } from "@/lib/users-query";
import { USER_STATUS_LABELS, USER_TYPE_LABELS } from "@/types/access-control";
import type { UsersModuleResult, UsersQuery } from "@/types/users";

type Props = {
  query: UsersQuery;
  facets: UsersModuleResult["facets"];
  basePath?: "/users" | "/staff";
};

export function UsersFilterBar({ query, facets, basePath = "/users" }: Props) {
  const router = useDashboardRouter();
  const [pending, startTransition] = useTransition();
  const [draft, setDraft] = useState(query);
  const [moreOpen, setMoreOpen] = useState(false);
  const isStaff = basePath === "/staff" || query.directoryScope === "staff";

  useEffect(() => {
    setDraft(query);
  }, [query]);

  const pushQuery = useCallback(
    (next: UsersQuery) => {
      const href = `${basePath}${usersQueryToSearchParams({
        ...next,
        directoryScope: isStaff ? "staff" : next.directoryScope,
      })}`;
      startTransition(() => router.push(href));
    },
    [router, basePath, isStaff],
  );

  const apply = () => pushQuery({ ...draft, page: 1 });
  const reset = () => {
    const cleared: UsersQuery = {
      ...query,
      search: "",
      status: "all",
      userType: "all",
      department: "",
      agency: "",
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
      directoryScope: isStaff ? "staff" : "users",
    };
    setDraft(cleared);
    pushQuery(cleared);
  };

  const activeCount = countActiveUserFilters(query);
  const moreActive =
    (query.department ? 1 : 0) +
    (query.agency ? 1 : 0) +
    (query.role ? 1 : 0) +
    (query.mfa !== "all" ? 1 : 0) +
    (query.verification !== "all" ? 1 : 0) +
    (query.securityState !== "all" ? 1 : 0) +
    (query.validationState !== "all" ? 1 : 0);

  return (
    <div className="space-y-3 rounded-2xl border border-jp-border bg-white p-3 shadow-sm" data-testid={isStaff ? "staff-filters" : "users-filters"}>
      <div className="flex flex-col gap-3 lg:flex-row lg:items-end">
        <div className="min-w-0 flex-1">
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
            placeholder={isStaff ? "Name, email, job titleâ€¦" : "Name, email, titleâ€¦"}
          />
        </div>
        {isStaff ? null : (
          <div className="w-full lg:w-44">
            <Label htmlFor="users-type">Account type</Label>
            <Select
              id="users-type"
              value={draft.userType}
              onChange={(e) => setDraft((d) => ({ ...d, userType: e.target.value as UsersQuery["userType"] }))}
            >
              <option value="all">All types</option>
              {facets.userTypes.map((t) => (
                <option key={t} value={t}>
                  {USER_TYPE_LABELS[t] ?? t}
                </option>
              ))}
            </Select>
          </div>
        )}
        <div className="w-full lg:w-44">
          <Label htmlFor="users-status">Status</Label>
          <Select
            id="users-status"
            value={draft.status}
            onChange={(e) => setDraft((d) => ({ ...d, status: e.target.value as UsersQuery["status"] }))}
          >
            <option value="all">All statuses</option>
            {facets.statuses.map((s) => (
              <option key={s} value={s}>
                {USER_STATUS_LABELS[s]}
              </option>
            ))}
          </Select>
        </div>
        <div className="flex flex-wrap gap-2">
          <Button
            variant="secondary"
            size="sm"
            type="button"
            aria-expanded={moreOpen}
            onClick={() => setMoreOpen((v) => !v)}
          >
            More filters{moreActive > 0 ? ` (${moreActive})` : ""}
          </Button>
          <Button variant="ghost" size="sm" type="button" onClick={reset}>
            Reset
          </Button>
          <Button size="sm" type="button" onClick={apply} disabled={pending} aria-busy={pending}>
            Apply
          </Button>
        </div>
      </div>

      {moreOpen ? (
        <div className="grid gap-3 border-t border-jp-border pt-3 sm:grid-cols-2 xl:grid-cols-3" data-testid="users-more-filters">
          <div>
            <Label htmlFor="users-department">Department</Label>
            <Select
              id="users-department"
              value={draft.department}
              onChange={(e) => setDraft((d) => ({ ...d, department: e.target.value }))}
            >
              <option value="">All departments</option>
              {facets.departments.map((d) => (
                <option key={d} value={d}>
                  {d}
                </option>
              ))}
            </Select>
          </div>
          {isStaff ? null : (
            <div>
              <Label htmlFor="users-agency">Agency</Label>
              <input
                id="users-agency"
                type="search"
                className="mt-1 w-full min-h-11 rounded-xl border border-jp-border px-3 text-sm"
                value={draft.agency}
                onChange={(e) => setDraft((d) => ({ ...d, agency: e.target.value }))}
                placeholder="Agency nameâ€¦"
              />
            </div>
          )}
          <div>
            <Label htmlFor="users-role">{isStaff ? "Job title" : "Role"}</Label>
            <Select
              id="users-role"
              value={draft.role}
              onChange={(e) => setDraft((d) => ({ ...d, role: e.target.value }))}
            >
              <option value="">{isStaff ? "All job titles" : "All roles"}</option>
              {facets.roles.map((r) => (
                <option key={r.id} value={r.id}>
                  {r.name}
                </option>
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
              <option value="all">All verification</option>
              <option value="verified">Verified</option>
              <option value="unverified">Unverified</option>
              <option value="pending">Pending</option>
            </Select>
          </div>
        </div>
      ) : null}

      {activeCount > 0 ? (
        <p className="text-xs text-jp-muted">{activeCount} active filter{activeCount === 1 ? "" : "s"}</p>
      ) : null}
    </div>
  );
}
