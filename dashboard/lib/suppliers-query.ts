import type {
  CredentialStatus,
  IntegrationStatus,
  OperationalStatus,
  SettlementStatus,
  SupplierCategory,
  SupplierSortField,
  SuppliersQuery,
  SortDirection,
} from "@/types/supplier";

const DEFAULT_PAGE_SIZE = 20;

const CATEGORIES: SupplierCategory[] = [
  "Airline",
  "GDS",
  "NDC",
  "Hotel",
  "Ground Transport",
  "Visa Service",
  "Insurance",
  "Ancillary Service",
  "Payment Service",
  "Consolidator",
];
const OPERATIONAL_STATUSES: OperationalStatus[] = [
  "Active",
  "Inactive",
  "Maintenance",
  "Restricted",
  "Review Required",
];
const INTEGRATION_STATUSES: IntegrationStatus[] = [
  "Connected",
  "Mock Only",
  "Manual",
  "Degraded",
  "Disabled",
];
const CREDENTIAL_STATUSES: CredentialStatus[] = [
  "Configured",
  "Missing",
  "Expiring Soon",
  "Invalid",
  "Not Required",
];
const SETTLEMENT_STATUSES: SettlementStatus[] = [
  "Current",
  "Due",
  "Overdue",
  "Reconciliation Required",
  "Not Applicable",
];
const SORT_FIELDS: SupplierSortField[] = [
  "supplierName",
  "newest",
  "bookingCount",
  "totalBookingValue",
  "totalPaid",
  "outstandingSettlement",
  "lastActivity",
  "statusPriority",
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

export function parseSuppliersQuery(
  searchParams: Record<string, string | string[] | undefined>,
): SuppliersQuery {
  const sortRaw = first(searchParams.sort);
  const directionRaw = first(searchParams.direction);

  return {
    q: first(searchParams.q).trim(),
    category: parseEnum(first(searchParams.category), CATEGORIES, "all"),
    operationalStatus: parseEnum(first(searchParams.operationalStatus), OPERATIONAL_STATUSES, "all"),
    integrationStatus: parseEnum(first(searchParams.integrationStatus), INTEGRATION_STATUSES, "all"),
    credentialStatus: parseEnum(first(searchParams.credentialStatus), CREDENTIAL_STATUSES, "all"),
    settlementStatus: parseEnum(first(searchParams.settlementStatus), SETTLEMENT_STATUSES, "all"),
    operatingRegion: first(searchParams.operatingRegion),
    hasOutstandingSettlement: parseYesNo(first(searchParams.hasOutstandingSettlement)),
    activityFrom: first(searchParams.activityFrom),
    activityTo: first(searchParams.activityTo),
    page: parsePositiveInt(first(searchParams.page), 1),
    pageSize: parsePageSize(first(searchParams.pageSize)),
    sort: SORT_FIELDS.includes(sortRaw as SupplierSortField)
      ? (sortRaw as SupplierSortField)
      : "supplierName",
    direction: directionRaw === "asc" || directionRaw === "desc" ? directionRaw : "asc",
    selectedId: first(searchParams.id) || null,
    previewError: first(searchParams.previewError) === "1",
    previewLoading: first(searchParams.previewLoading) === "1",
  };
}

export function suppliersQueryToSearchParams(
  query: SuppliersQuery,
  overrides?: Partial<SuppliersQuery>,
): string {
  const merged = { ...query, ...overrides };
  const params = new URLSearchParams();

  if (merged.q) params.set("q", merged.q);
  if (merged.category !== "all") params.set("category", merged.category);
  if (merged.operationalStatus !== "all")
    params.set("operationalStatus", merged.operationalStatus);
  if (merged.integrationStatus !== "all")
    params.set("integrationStatus", merged.integrationStatus);
  if (merged.credentialStatus !== "all") params.set("credentialStatus", merged.credentialStatus);
  if (merged.settlementStatus !== "all") params.set("settlementStatus", merged.settlementStatus);
  if (merged.operatingRegion) params.set("operatingRegion", merged.operatingRegion);
  if (merged.hasOutstandingSettlement !== "all")
    params.set("hasOutstandingSettlement", merged.hasOutstandingSettlement);
  if (merged.activityFrom) params.set("activityFrom", merged.activityFrom);
  if (merged.activityTo) params.set("activityTo", merged.activityTo);
  if (merged.page > 1) params.set("page", String(merged.page));
  if (merged.pageSize !== DEFAULT_PAGE_SIZE) params.set("pageSize", String(merged.pageSize));
  if (merged.sort !== "supplierName") params.set("sort", merged.sort);
  if (merged.direction !== "asc") params.set("direction", merged.direction);
  if (merged.selectedId) params.set("id", merged.selectedId);
  if (merged.previewError) params.set("previewError", "1");
  if (merged.previewLoading) params.set("previewLoading", "1");

  const s = params.toString();
  return s ? `?${s}` : "";
}

export function defaultSuppliersQuery(): SuppliersQuery {
  return parseSuppliersQuery({});
}

export type { SortDirection };
