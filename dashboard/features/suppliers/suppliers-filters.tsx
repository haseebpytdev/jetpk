"use client";

import { useCallback, useEffect, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { DateInput, SearchInput } from "@/components/ui/input";
import { Label } from "@/components/ui/page-layout";
import { Select } from "@/components/ui/select";
import { countActiveSupplierFilters } from "@/lib/suppliers-filter";
import { suppliersQueryToSearchParams } from "@/lib/suppliers-query";
import type { SuppliersPageResult, SuppliersQuery } from "@/types/supplier";

type Props = {
  query: SuppliersQuery;
  facets: SuppliersPageResult["facets"];
};

export function SuppliersFilters({ query, facets }: Props) {
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const [draft, setDraft] = useState(query);

  useEffect(() => {
    setDraft(query);
  }, [query]);

  const pushQuery = useCallback(
    (next: SuppliersQuery) => {
      const href = `/suppliers${suppliersQueryToSearchParams(next)}`;
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
      category: "all",
      operationalStatus: "all",
      integrationStatus: "all",
      credentialStatus: "all",
      settlementStatus: "all",
      operatingRegion: "",
      hasOutstandingSettlement: "all",
      activityFrom: "",
      activityTo: "",
      page: 1,
    });
    setDraft((d) => ({
      ...d,
      q: "",
      category: "all",
      operationalStatus: "all",
      integrationStatus: "all",
      credentialStatus: "all",
      settlementStatus: "all",
      operatingRegion: "",
      hasOutstandingSettlement: "all",
      activityFrom: "",
      activityTo: "",
    }));
  };

  const activeCount = countActiveSupplierFilters(query);

  return (
    <Card className="space-y-4" data-testid="suppliers-filters">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h2 className="text-sm font-semibold text-gray-900">Filters</h2>
        <div className="flex flex-wrap items-center gap-2 text-sm">
          {activeCount > 0 ? (
            <span className="rounded-full bg-jp-accent/10 px-2.5 py-1 text-xs font-medium text-jp-accent-muted">
              {activeCount} active
            </span>
          ) : null}
          <Button variant="ghost" size="sm" type="button" onClick={clearAll} disabled={activeCount === 0}>
            Clear all
          </Button>
        </div>
      </div>

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div className="sm:col-span-2 xl:col-span-2">
          <Label htmlFor="suppliers-search">Search</Label>
          <SearchInput
            id="suppliers-search"
            placeholder="Supplier ID, name, code, region, booking…"
            value={draft.q}
            onChange={(e) => setDraft((d) => ({ ...d, q: e.target.value }))}
            onClear={() => setDraft((d) => ({ ...d, q: "" }))}
            onKeyDown={(e) => {
              if (e.key === "Enter") apply();
            }}
          />
        </div>
        <div>
          <Label htmlFor="filter-category">Category</Label>
          <Select
            id="filter-category"
            value={draft.category}
            onChange={(e) =>
              setDraft((d) => ({ ...d, category: e.target.value as SuppliersQuery["category"] }))
            }
          >
            <option value="all">All</option>
            {facets.categories.map((c) => (
              <option key={c} value={c}>
                {c}
              </option>
            ))}
          </Select>
        </div>
        <div>
          <Label htmlFor="filter-operational-status">Operational status</Label>
          <Select
            id="filter-operational-status"
            value={draft.operationalStatus}
            onChange={(e) =>
              setDraft((d) => ({
                ...d,
                operationalStatus: e.target.value as SuppliersQuery["operationalStatus"],
              }))
            }
          >
            <option value="all">All</option>
            {facets.operationalStatuses.map((s) => (
              <option key={s} value={s}>
                {s}
              </option>
            ))}
          </Select>
        </div>
        <div>
          <Label htmlFor="filter-integration-status">Integration status</Label>
          <Select
            id="filter-integration-status"
            value={draft.integrationStatus}
            onChange={(e) =>
              setDraft((d) => ({
                ...d,
                integrationStatus: e.target.value as SuppliersQuery["integrationStatus"],
              }))
            }
          >
            <option value="all">All</option>
            <option value="Connected">Connected</option>
            <option value="Mock Only">Mock Only</option>
            <option value="Manual">Manual</option>
            <option value="Degraded">Degraded</option>
            <option value="Disabled">Disabled</option>
          </Select>
        </div>
        <div>
          <Label htmlFor="filter-credential-status">Credential status</Label>
          <Select
            id="filter-credential-status"
            value={draft.credentialStatus}
            onChange={(e) =>
              setDraft((d) => ({
                ...d,
                credentialStatus: e.target.value as SuppliersQuery["credentialStatus"],
              }))
            }
          >
            <option value="all">All</option>
            <option value="Configured">Configured</option>
            <option value="Missing">Missing</option>
            <option value="Expiring Soon">Expiring Soon</option>
            <option value="Invalid">Invalid</option>
            <option value="Not Required">Not Required</option>
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
                settlementStatus: e.target.value as SuppliersQuery["settlementStatus"],
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
          <Label htmlFor="filter-region">Operating region</Label>
          <Select
            id="filter-region"
            value={draft.operatingRegion}
            onChange={(e) => setDraft((d) => ({ ...d, operatingRegion: e.target.value }))}
          >
            <option value="">All</option>
            {facets.regions.map((r) => (
              <option key={r} value={r}>
                {r}
              </option>
            ))}
          </Select>
        </div>
        <div>
          <Label htmlFor="filter-outstanding-settlement">Outstanding settlement</Label>
          <Select
            id="filter-outstanding-settlement"
            value={draft.hasOutstandingSettlement}
            onChange={(e) =>
              setDraft((d) => ({
                ...d,
                hasOutstandingSettlement: e.target.value as SuppliersQuery["hasOutstandingSettlement"],
              }))
            }
          >
            <option value="all">All</option>
            <option value="yes">Has outstanding</option>
            <option value="no">No outstanding</option>
          </Select>
        </div>
        <div>
          <Label htmlFor="supplier-activity-from">Activity from</Label>
          <DateInput
            id="supplier-activity-from"
            value={draft.activityFrom}
            onChange={(e) => setDraft((d) => ({ ...d, activityFrom: e.target.value }))}
          />
        </div>
        <div>
          <Label htmlFor="supplier-activity-to">Activity to</Label>
          <DateInput
            id="supplier-activity-to"
            value={draft.activityTo}
            onChange={(e) => setDraft((d) => ({ ...d, activityTo: e.target.value }))}
          />
        </div>
      </div>

      <div className="flex flex-wrap gap-2">
        <Button type="button" onClick={apply} disabled={pending} aria-busy={pending}>
          Apply filters
        </Button>
      </div>
    </Card>
  );
}
