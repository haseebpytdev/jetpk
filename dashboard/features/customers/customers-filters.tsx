"use client";

import { useCallback, useEffect, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { DateInput, SearchInput } from "@/components/ui/input";
import { Label } from "@/components/ui/page-layout";
import { Select } from "@/components/ui/select";
import { countActiveCustomerFilters } from "@/lib/customers-filter";
import { customersQueryToSearchParams } from "@/lib/customers-query";
import type { CustomersPageResult, CustomersQuery } from "@/types/customer";

type Props = {
  query: CustomersQuery;
  facets: CustomersPageResult["facets"];
};

export function CustomersFilters({ query, facets }: Props) {
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const [draft, setDraft] = useState(query);

  useEffect(() => {
    setDraft(query);
  }, [query]);

  const pushQuery = useCallback(
    (next: CustomersQuery) => {
      const href = `/customers${customersQueryToSearchParams(next)}`;
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
      customerType: "all",
      city: "",
      country: "",
      hasOutstandingBalance: "all",
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
      customerType: "all",
      city: "",
      country: "",
      hasOutstandingBalance: "all",
      hasBookings: "all",
      activityFrom: "",
      activityTo: "",
    }));
  };

  const activeCount = countActiveCustomerFilters(query);

  return (
    <Card className="space-y-4" data-testid="customers-filters">
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
          <Label htmlFor="customers-search">Search</Label>
          <SearchInput
            id="customers-search"
            placeholder="Customer ID, name, email, phone, booking…"
            value={draft.q}
            onChange={(e) => setDraft((d) => ({ ...d, q: e.target.value }))}
            onClear={() => setDraft((d) => ({ ...d, q: "" }))}
            onKeyDown={(e) => {
              if (e.key === "Enter") apply();
            }}
          />
        </div>
        <div>
          <Label htmlFor="filter-account-status">Account status</Label>
          <Select
            id="filter-account-status"
            value={draft.accountStatus}
            onChange={(e) =>
              setDraft((d) => ({ ...d, accountStatus: e.target.value as CustomersQuery["accountStatus"] }))
            }
          >
            <option value="all">All</option>
            <option value="Active">Active</option>
            <option value="Inactive">Inactive</option>
            <option value="Suspended">Suspended</option>
            <option value="Review Required">Review Required</option>
          </Select>
        </div>
        <div>
          <Label htmlFor="filter-verification-status">Verification status</Label>
          <Select
            id="filter-verification-status"
            value={draft.verificationStatus}
            onChange={(e) =>
              setDraft((d) => ({
                ...d,
                verificationStatus: e.target.value as CustomersQuery["verificationStatus"],
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
          <Label htmlFor="filter-customer-type">Customer type</Label>
          <Select
            id="filter-customer-type"
            value={draft.customerType}
            onChange={(e) =>
              setDraft((d) => ({ ...d, customerType: e.target.value as CustomersQuery["customerType"] }))
            }
          >
            <option value="all">All</option>
            {facets.customerTypes.map((t) => (
              <option key={t} value={t}>
                {t}
              </option>
            ))}
          </Select>
        </div>
        <div>
          <Label htmlFor="filter-city">City</Label>
          <Select
            id="filter-city"
            value={draft.city}
            onChange={(e) => setDraft((d) => ({ ...d, city: e.target.value }))}
          >
            <option value="">All</option>
            {facets.cities.map((c) => (
              <option key={c} value={c}>
                {c}
              </option>
            ))}
          </Select>
        </div>
        <div>
          <Label htmlFor="filter-country">Country</Label>
          <Select
            id="filter-country"
            value={draft.country}
            onChange={(e) => setDraft((d) => ({ ...d, country: e.target.value }))}
          >
            <option value="">All</option>
            {facets.countries.map((c) => (
              <option key={c} value={c}>
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
                hasOutstandingBalance: e.target.value as CustomersQuery["hasOutstandingBalance"],
              }))
            }
          >
            <option value="all">All</option>
            <option value="yes">Has outstanding</option>
            <option value="no">No outstanding</option>
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
                hasBookings: e.target.value as CustomersQuery["hasBookings"],
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

      <div className="flex flex-wrap gap-2">
        <Button type="button" onClick={apply} disabled={pending} aria-busy={pending}>
          Apply filters
        </Button>
      </div>
    </Card>
  );
}
