import type {
  CustomerRecord,
  CustomersPageResult,
  CustomersQuery,
  CustomersSummaryMetrics,
} from "@/types/customer";
import { mockCustomers } from "@/mocks/customer-fixtures";

function matchesSearch(customer: CustomerRecord, q: string): boolean {
  if (!q) return true;
  const needle = q.toLowerCase();
  const haystack = [
    customer.id,
    customer.fullName,
    customer.email,
    customer.phone,
    customer.city,
    customer.country,
    customer.nationality,
    customer.notesSummary,
    ...customer.linkedBookingIds,
    ...customer.linkedTransactionIds,
  ]
    .join(" ")
    .toLowerCase();
  return haystack.includes(needle);
}

function inDateRange(value: string | null, from: string, to: string): boolean {
  if (!value) return !from && !to;
  if (from && value < from) return false;
  if (to && value > to) return false;
  return true;
}

function activityDate(customer: CustomerRecord): string | null {
  return customer.lastBookingDate ?? customer.lastPaymentDate ?? customer.createdDate;
}

export function filterCustomers(all: CustomerRecord[], query: CustomersQuery): CustomerRecord[] {
  return all.filter((customer) => {
    if (!matchesSearch(customer, query.q)) return false;
    if (query.accountStatus !== "all" && customer.accountStatus !== query.accountStatus) return false;
    if (query.verificationStatus !== "all" && customer.verificationStatus !== query.verificationStatus)
      return false;
    if (query.customerType !== "all" && customer.customerType !== query.customerType) return false;
    if (query.city && customer.city !== query.city) return false;
    if (query.country && customer.country !== query.country) return false;
    if (query.hasOutstandingBalance === "yes" && customer.outstandingBalance <= 0) return false;
    if (query.hasOutstandingBalance === "no" && customer.outstandingBalance > 0) return false;
    if (query.hasBookings === "yes" && customer.bookingCount === 0) return false;
    if (query.hasBookings === "no" && customer.bookingCount > 0) return false;
    if (!inDateRange(activityDate(customer), query.activityFrom, query.activityTo)) return false;
    return true;
  });
}

function compareStrings(a: string, b: string): number {
  return a.localeCompare(b, "en", { sensitivity: "base" });
}

function compareNullableDates(a: string | null, b: string | null): number {
  if (!a && !b) return 0;
  if (!a) return 1;
  if (!b) return -1;
  return compareStrings(a, b);
}

export function sortCustomers(
  rows: CustomerRecord[],
  sort: CustomersQuery["sort"],
  direction: CustomersQuery["direction"],
): CustomerRecord[] {
  const sorted = [...rows].sort((a, b) => {
    let cmp = 0;
    switch (sort) {
      case "name":
        cmp = compareStrings(a.fullName, b.fullName);
        break;
      case "newest":
        cmp = compareStrings(b.createdDate, a.createdDate);
        break;
      case "oldest":
        cmp = compareStrings(a.createdDate, b.createdDate);
        break;
      case "bookingCount":
        cmp = a.bookingCount - b.bookingCount;
        break;
      case "totalBookedValue":
        cmp = a.totalBookedValue - b.totalBookedValue;
        break;
      case "totalPaid":
        cmp = a.totalPaid - b.totalPaid;
        break;
      case "outstandingBalance":
        cmp = a.outstandingBalance - b.outstandingBalance;
        break;
      case "lastBookingDate":
        cmp = compareNullableDates(a.lastBookingDate, b.lastBookingDate);
        break;
      default:
        cmp = 0;
    }
    if (cmp === 0) {
      cmp = compareStrings(a.id, b.id);
    }
    return direction === "asc" ? cmp : -cmp;
  });
  return sorted;
}

export function computeCustomersSummary(rows: CustomerRecord[]): CustomersSummaryMetrics {
  const recentCutoff = "2026-01-01";
  let activeCustomers = 0;
  let totalTravellers = 0;
  let customersWithOutstanding = 0;
  let totalLifetimeValue = 0;
  let recentCustomers = 0;

  for (const customer of rows) {
    if (customer.accountStatus === "Active") activeCustomers += 1;
    totalTravellers += customer.travellerCount;
    if (customer.outstandingBalance > 0) customersWithOutstanding += 1;
    totalLifetimeValue += customer.totalBookedValue;
    if (customer.createdDate >= recentCutoff) recentCustomers += 1;
  }

  return {
    totalCustomers: rows.length,
    activeCustomers,
    totalTravellers,
    customersWithOutstanding,
    totalLifetimeValue,
    recentCustomers,
    currency: "PKR",
  };
}

export function paginateCustomers(
  rows: CustomerRecord[],
  page: number,
  pageSize: number,
): { page: number; pageCount: number; slice: CustomerRecord[] } {
  const pageCount = Math.max(1, Math.ceil(rows.length / pageSize));
  const clampedPage = Math.min(Math.max(1, page), pageCount);
  const start = (clampedPage - 1) * pageSize;
  return {
    page: clampedPage,
    pageCount,
    slice: rows.slice(start, start + pageSize),
  };
}

export function buildCustomersPage(
  query: CustomersQuery,
  all: CustomerRecord[] = mockCustomers,
): CustomersPageResult {
  const filtered = filterCustomers(all, query);
  const sorted = sortCustomers(filtered, query.sort, query.direction);
  const { page, pageCount, slice } = paginateCustomers(sorted, query.page, query.pageSize);
  const cities = [...new Set(all.map((c) => c.city))].sort();
  const countries = [...new Set(all.map((c) => c.country))].sort();
  const customerTypes = [...new Set(all.map((c) => c.customerType))].sort();

  return {
    customers: slice,
    total: filtered.length,
    page,
    pageSize: query.pageSize,
    pageCount,
    summary: computeCustomersSummary(filtered),
    facets: { cities, countries, customerTypes },
  };
}

export function countActiveCustomerFilters(query: CustomersQuery): number {
  let n = 0;
  if (query.q) n += 1;
  if (query.accountStatus !== "all") n += 1;
  if (query.verificationStatus !== "all") n += 1;
  if (query.customerType !== "all") n += 1;
  if (query.city) n += 1;
  if (query.country) n += 1;
  if (query.hasOutstandingBalance !== "all") n += 1;
  if (query.hasBookings !== "all") n += 1;
  if (query.activityFrom || query.activityTo) n += 1;
  return n;
}
