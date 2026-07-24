import type {
  AccountStatus,
  CustomerSortField,
  CustomersQuery,
  CustomerType,
  SortDirection,
  VerificationStatus,
} from "@/types/customer";

const DEFAULT_PAGE_SIZE = 20;

const ACCOUNT_STATUSES: AccountStatus[] = ["Active", "Inactive", "Suspended", "Review Required"];
const VERIFICATION_STATUSES: VerificationStatus[] = [
  "Verified",
  "Pending",
  "Incomplete",
  "Not Required",
];
const CUSTOMER_TYPES: CustomerType[] = [
  "Individual",
  "Family",
  "Corporate Contact",
  "Agent-Referred",
];
const SORT_FIELDS: CustomerSortField[] = [
  "name",
  "newest",
  "oldest",
  "bookingCount",
  "totalBookedValue",
  "totalPaid",
  "outstandingBalance",
  "lastBookingDate",
];

function first(value: string | string[] | undefined): string {
  if (Array.isArray(value)) {
    return value[0] ?? "";
  }
  return value ?? "";
}

function parseEnum<T extends string>(raw: string, allowed: readonly T[], fallback: T | "all"): T | "all" {
  if (!raw || raw === "all") {
    return fallback;
  }
  return (allowed as readonly string[]).includes(raw) ? (raw as T) : fallback;
}

function parsePositiveInt(raw: string, fallback: number, max?: number): number {
  const n = Number.parseInt(raw, 10);
  if (!Number.isFinite(n) || n < 1) {
    return fallback;
  }
  if (max !== undefined && n > max) {
    return max;
  }
  return n;
}

function parsePageSize(raw: string): number {
  const n = parsePositiveInt(raw, DEFAULT_PAGE_SIZE);
  if (n === 10 || n === 20 || n === 50) {
    return n;
  }
  return DEFAULT_PAGE_SIZE;
}

function parseYesNo(raw: string): "all" | "yes" | "no" {
  if (raw === "yes" || raw === "no") return raw;
  return "all";
}

export function parseCustomersQuery(
  searchParams: Record<string, string | string[] | undefined>,
): CustomersQuery {
  const sortRaw = first(searchParams.sort);
  const directionRaw = first(searchParams.direction);

  return {
    q: first(searchParams.q).trim(),
    accountStatus: parseEnum(first(searchParams.accountStatus), ACCOUNT_STATUSES, "all"),
    verificationStatus: parseEnum(
      first(searchParams.verificationStatus),
      VERIFICATION_STATUSES,
      "all",
    ),
    customerType: parseEnum(first(searchParams.customerType), CUSTOMER_TYPES, "all"),
    city: first(searchParams.city),
    country: first(searchParams.country),
    hasOutstandingBalance: parseYesNo(first(searchParams.hasOutstandingBalance)),
    hasBookings: parseYesNo(first(searchParams.hasBookings)),
    activityFrom: first(searchParams.activityFrom),
    activityTo: first(searchParams.activityTo),
    page: parsePositiveInt(first(searchParams.page), 1),
    pageSize: parsePageSize(first(searchParams.pageSize)),
    sort: SORT_FIELDS.includes(sortRaw as CustomerSortField)
      ? (sortRaw as CustomerSortField)
      : "name",
    direction: directionRaw === "asc" || directionRaw === "desc" ? directionRaw : "asc",
    selectedId: first(searchParams.id) || null,
    previewError: first(searchParams.previewError) === "1",
    previewLoading: first(searchParams.previewLoading) === "1",
  };
}

export function customersQueryToSearchParams(
  query: CustomersQuery,
  overrides?: Partial<CustomersQuery>,
): string {
  const merged = { ...query, ...overrides };
  const params = new URLSearchParams();

  if (merged.q) params.set("q", merged.q);
  if (merged.accountStatus !== "all") params.set("accountStatus", merged.accountStatus);
  if (merged.verificationStatus !== "all")
    params.set("verificationStatus", merged.verificationStatus);
  if (merged.customerType !== "all") params.set("customerType", merged.customerType);
  if (merged.city) params.set("city", merged.city);
  if (merged.country) params.set("country", merged.country);
  if (merged.hasOutstandingBalance !== "all")
    params.set("hasOutstandingBalance", merged.hasOutstandingBalance);
  if (merged.hasBookings !== "all") params.set("hasBookings", merged.hasBookings);
  if (merged.activityFrom) params.set("activityFrom", merged.activityFrom);
  if (merged.activityTo) params.set("activityTo", merged.activityTo);
  if (merged.page > 1) params.set("page", String(merged.page));
  if (merged.pageSize !== DEFAULT_PAGE_SIZE) params.set("pageSize", String(merged.pageSize));
  if (merged.sort !== "name") params.set("sort", merged.sort);
  if (merged.direction !== "asc") params.set("direction", merged.direction);
  if (merged.selectedId) params.set("id", merged.selectedId);
  if (merged.previewError) params.set("previewError", "1");
  if (merged.previewLoading) params.set("previewLoading", "1");

  const s = params.toString();
  return s ? `?${s}` : "";
}

export function defaultCustomersQuery(): CustomersQuery {
  return parseCustomersQuery({});
}

export type { SortDirection };
