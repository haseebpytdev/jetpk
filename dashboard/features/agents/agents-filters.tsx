"use client";

import { useCallback, useEffect, useState, useTransition } from "react";
import { useDashboardRouter } from "@/lib/dashboard-navigation";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { DateInput, SearchInput } from "@/components/ui/input";
import { Label } from "@/components/ui/page-layout";
import { Select } from "@/components/ui/select";
import { countActiveAgentFilters } from "@/lib/agents-filter";
import { agentsQueryToSearchParams } from "@/lib/agents-query";
import type { AgentsPageResult, AgentsQuery } from "@/types/agent";

type Props = {
  query: AgentsQuery;
  facets: AgentsPageResult["facets"];
};

export function AgentsFilters({ query, facets }: Props) {
  const router = useDashboardRouter();
  const [pending, startTransition] = useTransition();
  const [draft, setDraft] = useState(query);
  const [moreOpen, setMoreOpen] = useState(false);

  useEffect(() => {
    setDraft(query);
  }, [query]);

  const pushQuery = useCallback(
    (next: AgentsQuery) => {
      const href = `/agents${agentsQueryToSearchParams(next)}`;
      startTransition(() => {
        router.push(href);
      });
    },
    [router],
  );

  const apply = () => {
    pushQuery({ ...draft, page: 1 });
  };

  const clearAll = () => {
    pushQuery({
      ...query,
      q: "",
      accountStatus: "all",
      verificationStatus: "all",
      commercialStatus: "all",
      settlementStatus: "all",
      agentType: "all",
      city: "",
      countryRegion: "",
      hasOutstandingBalance: "all",
      hasPendingCommission: "all",
      hasBookings: "all",
      activityFrom: "",
      activityTo: "",
      page: 1,
    });
    setDraft((d) => ({
      ...d,
      q: "",
      accountStatus: "all",
      verificationStatus: "all",
      commercialStatus: "all",
      settlementStatus: "all",
      agentType: "all",
      city: "",
      countryRegion: "",
      hasOutstandingBalance: "all",
      hasPendingCommission: "all",
      hasBookings: "all",
      activityFrom: "",
      activityTo: "",
    }));
  };

  const activeCount = countActiveAgentFilters(query);

  const moreActive =
    (query.verificationStatus !== "all" ? 1 : 0) +
    (query.commercialStatus !== "all" ? 1 : 0) +
    (query.settlementStatus !== "all" ? 1 : 0) +
    (query.city ? 1 : 0) +
    (query.countryRegion ? 1 : 0) +
    (query.hasOutstandingBalance !== "all" ? 1 : 0) +
    (query.hasPendingCommission !== "all" ? 1 : 0) +
    (query.hasBookings !== "all" ? 1 : 0) +
    (query.activityFrom || query.activityTo ? 1 : 0);

  return (
    <Card className="space-y-3 p-3" data-testid="agents-filters">
      <div className="flex flex-col gap-3 lg:flex-row lg:items-end">
        <div className="min-w-0 flex-1">
          <Label htmlFor="agents-search">Search</Label>
          <SearchInput
            id="agents-search"
            placeholder="Agent ID, agency name, contact, booking…"
            value={draft.q}
            onChange={(e) => setDraft((d) => ({ ...d, q: e.target.value }))}
            onClear={() => setDraft((d) => ({ ...d, q: "" }))}
            onKeyDown={(e) => {
              if (e.key === "Enter") apply();
            }}
          />
        </div>
        <div className="w-full lg:w-44">
          <Label htmlFor="filter-account-status">Account status</Label>
          <Select
            id="filter-account-status"
            value={draft.accountStatus}
            onChange={(e) =>
              setDraft((d) => ({ ...d, accountStatus: e.target.value as AgentsQuery["accountStatus"] }))
            }
          >
            <option value="all">All</option>
            <option value="Active">Active</option>
            <option value="Inactive">Inactive</option>
            <option value="Suspended">Suspended</option>
            <option value="Review Required">Review Required</option>
          </Select>
        </div>
        <div className="w-full lg:w-44">
          <Label htmlFor="filter-agent-type">Agent type</Label>
          <Select
            id="filter-agent-type"
            value={draft.agentType}
            onChange={(e) =>
              setDraft((d) => ({ ...d, agentType: e.target.value as AgentsQuery["agentType"] }))
            }
          >
            <option value="all">All</option>
            {facets.agentTypes.map((t) => (
              <option key={t} value={t}>
                {t}
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
          <Button variant="ghost" size="sm" type="button" onClick={clearAll} disabled={activeCount === 0}>
            Reset
          </Button>
          <Button size="sm" type="button" onClick={apply} disabled={pending} aria-busy={pending}>
            Apply
          </Button>
        </div>
      </div>

      {moreOpen ? (
        <div
          className="grid gap-3 border-t border-jp-border pt-3 sm:grid-cols-2 xl:grid-cols-3"
          data-testid="agents-more-filters"
        >
          <div>
            <Label htmlFor="filter-verification-status">Verification</Label>
            <Select
              id="filter-verification-status"
              value={draft.verificationStatus}
              onChange={(e) =>
                setDraft((d) => ({
                  ...d,
                  verificationStatus: e.target.value as AgentsQuery["verificationStatus"],
                }))
              }
            >
              <option value="all">All</option>
              <option value="Verified">Verified</option>
              <option value="Pending">Pending</option>
              <option value="Incomplete">Incomplete</option>
              <option value="Not Required">Not Required</option>
            </Select>
          </div>
          <div>
            <Label htmlFor="filter-commercial-status">Commercial status</Label>
            <Select
              id="filter-commercial-status"
              value={draft.commercialStatus}
              onChange={(e) =>
                setDraft((d) => ({
                  ...d,
                  commercialStatus: e.target.value as AgentsQuery["commercialStatus"],
                }))
              }
            >
              <option value="all">All</option>
              <option value="Standard">Standard</option>
              <option value="Preferred">Preferred</option>
              <option value="Credit Enabled">Credit Enabled</option>
              <option value="Prepaid Only">Prepaid Only</option>
              <option value="On Hold">On Hold</option>
            </Select>
          </div>
          <div>
            <Label htmlFor="filter-settlement-status">Settlement status</Label>
            <Select
              id="filter-settlement-status"
              value={draft.settlementStatus}
              onChange={(e) =>
                setDraft((d) => ({
                  ...d,
                  settlementStatus: e.target.value as AgentsQuery["settlementStatus"],
                }))
              }
            >
              <option value="all">All</option>
              <option value="Current">Current</option>
              <option value="Due">Due</option>
              <option value="Overdue">Overdue</option>
              <option value="Reconciliation Required">Reconciliation Required</option>
              <option value="Not Applicable">Not Applicable</option>
            </Select>
          </div>
          <div>
            <Label htmlFor="filter-city">City</Label>
            <Select id="filter-city" value={draft.city} onChange={(e) => setDraft((d) => ({ ...d, city: e.target.value }))}>
              <option value="">All</option>
              {facets.cities.map((c) => (
                <option key={c} value={c}>
                  {c}
                </option>
              ))}
            </Select>
          </div>
          <div>
            <Label htmlFor="filter-country-region">Country</Label>
            <Select
              id="filter-country-region"
              value={draft.countryRegion}
              onChange={(e) => setDraft((d) => ({ ...d, countryRegion: e.target.value }))}
            >
              <option value="">All</option>
              {facets.countries.map((c) => (
                <option key={`country-${c}`} value={c}>
                  {c}
                </option>
              ))}
            </Select>
          </div>
          <div>
            <Label htmlFor="filter-outstanding">Outstanding balance</Label>
            <Select
              id="filter-outstanding"
              value={draft.hasOutstandingBalance}
              onChange={(e) =>
                setDraft((d) => ({
                  ...d,
                  hasOutstandingBalance: e.target.value as AgentsQuery["hasOutstandingBalance"],
                }))
              }
            >
              <option value="all">All</option>
              <option value="yes">Has outstanding</option>
              <option value="no">No outstanding</option>
            </Select>
          </div>
          <div>
            <Label htmlFor="filter-pending-commission">Pending commission</Label>
            <Select
              id="filter-pending-commission"
              value={draft.hasPendingCommission}
              onChange={(e) =>
                setDraft((d) => ({
                  ...d,
                  hasPendingCommission: e.target.value as AgentsQuery["hasPendingCommission"],
                }))
              }
            >
              <option value="all">All</option>
              <option value="yes">Has pending</option>
              <option value="no">No pending</option>
            </Select>
          </div>
          <div>
            <Label htmlFor="filter-has-bookings">Has bookings</Label>
            <Select
              id="filter-has-bookings"
              value={draft.hasBookings}
              onChange={(e) =>
                setDraft((d) => ({
                  ...d,
                  hasBookings: e.target.value as AgentsQuery["hasBookings"],
                }))
              }
            >
              <option value="all">All</option>
              <option value="yes">Has bookings</option>
              <option value="no">No bookings</option>
            </Select>
          </div>
          <div>
            <Label htmlFor="activity-from">Activity from</Label>
            <DateInput
              id="activity-from"
              value={draft.activityFrom}
              onChange={(e) => setDraft((d) => ({ ...d, activityFrom: e.target.value }))}
            />
          </div>
          <div>
            <Label htmlFor="activity-to">Activity to</Label>
            <DateInput
              id="activity-to"
              value={draft.activityTo}
              onChange={(e) => setDraft((d) => ({ ...d, activityTo: e.target.value }))}
            />
          </div>
        </div>
      ) : null}

      {activeCount > 0 ? (
        <p className="text-xs text-jp-muted">
          {activeCount} active filter{activeCount === 1 ? "" : "s"}
        </p>
      ) : null}
    </Card>
  );
}
