import type {
  SupplierRecord,
  SuppliersPageResult,
  SuppliersQuery,
  SuppliersSummaryMetrics,
} from "@/types/supplier";
import { mockSuppliers } from "@/mocks/supplier-fixtures";

function matchesSearch(supplier: SupplierRecord, q: string): boolean {
  if (!q) return true;
  const needle = q.toLowerCase();
  const haystack = [
    supplier.id,
    supplier.supplierName,
    supplier.displayCode,
    supplier.operatingRegion,
    supplier.supportContact,
    supplier.notesSummary,
    ...supplier.linkedBookingIds,
    ...supplier.linkedTransactionIds,
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

function activityDate(supplier: SupplierRecord): string | null {
  return supplier.lastBookingActivity ?? supplier.lastSettlementActivity ?? supplier.createdDate;
}

const STATUS_PRIORITY: Record<string, number> = {
  Active: 0,
  Maintenance: 1,
  Restricted: 2,
  "Review Required": 3,
  Inactive: 4,
};

export function filterSuppliers(all: SupplierRecord[], query: SuppliersQuery): SupplierRecord[] {
  return all.filter((supplier) => {
    if (!matchesSearch(supplier, query.q)) return false;
    if (query.category !== "all" && supplier.supplierCategory !== query.category) return false;
    if (query.operationalStatus !== "all" && supplier.operationalStatus !== query.operationalStatus)
      return false;
    if (query.integrationStatus !== "all" && supplier.integrationStatus !== query.integrationStatus)
      return false;
    if (query.credentialStatus !== "all" && supplier.credentialStatus !== query.credentialStatus)
      return false;
    if (query.settlementStatus !== "all" && supplier.settlementStatus !== query.settlementStatus)
      return false;
    if (query.operatingRegion && supplier.operatingRegion !== query.operatingRegion) return false;
    if (query.hasOutstandingSettlement === "yes" && supplier.outstandingSettlement <= 0) return false;
    if (query.hasOutstandingSettlement === "no" && supplier.outstandingSettlement > 0) return false;
    if (!inDateRange(activityDate(supplier), query.activityFrom, query.activityTo)) return false;
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

export function sortSuppliers(
  rows: SupplierRecord[],
  sort: SuppliersQuery["sort"],
  direction: SuppliersQuery["direction"],
): SupplierRecord[] {
  const sorted = [...rows].sort((a, b) => {
    let cmp = 0;
    switch (sort) {
      case "supplierName":
        cmp = compareStrings(a.supplierName, b.supplierName);
        break;
      case "newest":
        cmp = compareStrings(b.createdDate, a.createdDate);
        break;
      case "bookingCount":
        cmp = a.bookingCount - b.bookingCount;
        break;
      case "totalBookingValue":
        cmp = a.totalBookingValue - b.totalBookingValue;
        break;
      case "totalPaid":
        cmp = a.totalPaidToSupplier - b.totalPaidToSupplier;
        break;
      case "outstandingSettlement":
        cmp = a.outstandingSettlement - b.outstandingSettlement;
        break;
      case "lastActivity":
        cmp = compareNullableDates(a.lastBookingActivity, b.lastBookingActivity);
        break;
      case "statusPriority":
        cmp =
          (STATUS_PRIORITY[a.operationalStatus] ?? 99) - (STATUS_PRIORITY[b.operationalStatus] ?? 99);
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

export function computeSuppliersSummary(rows: SupplierRecord[]): SuppliersSummaryMetrics {
  const recentCutoff = "2026-01-01";
  let activeSuppliers = 0;
  let connectedSuppliers = 0;
  let suppliersRequiringReview = 0;
  let totalOutstandingSettlements = 0;
  let recentSupplierActivity = 0;

  for (const supplier of rows) {
    if (supplier.operationalStatus === "Active") activeSuppliers += 1;
    if (supplier.integrationStatus === "Connected") connectedSuppliers += 1;
    if (
      supplier.operationalStatus === "Review Required" ||
      supplier.settlementStatus === "Reconciliation Required" ||
      supplier.settlementStatus === "Overdue"
    ) {
      suppliersRequiringReview += 1;
    }
    totalOutstandingSettlements += supplier.outstandingSettlement;
    const lastActivity = activityDate(supplier);
    if (lastActivity && lastActivity >= recentCutoff) recentSupplierActivity += 1;
  }

  return {
    totalSuppliers: rows.length,
    activeSuppliers,
    connectedSuppliers,
    suppliersRequiringReview,
    totalOutstandingSettlements,
    recentSupplierActivity,
    currency: "PKR",
  };
}

export function paginateSuppliers(
  rows: SupplierRecord[],
  page: number,
  pageSize: number,
): { page: number; pageCount: number; slice: SupplierRecord[] } {
  const pageCount = Math.max(1, Math.ceil(rows.length / pageSize));
  const clampedPage = Math.min(Math.max(1, page), pageCount);
  const start = (clampedPage - 1) * pageSize;
  return {
    page: clampedPage,
    pageCount,
    slice: rows.slice(start, start + pageSize),
  };
}

export function buildSuppliersPage(
  query: SuppliersQuery,
  all: SupplierRecord[] = mockSuppliers,
): SuppliersPageResult {
  const filtered = filterSuppliers(all, query);
  const sorted = sortSuppliers(filtered, query.sort, query.direction);
  const { page, pageCount, slice } = paginateSuppliers(sorted, query.page, query.pageSize);
  const categories = [...new Set(all.map((s) => s.supplierCategory))].sort();
  const regions = [...new Set(all.map((s) => s.operatingRegion))].sort();
  const operationalStatuses = [...new Set(all.map((s) => s.operationalStatus))].sort();

  return {
    suppliers: slice,
    total: filtered.length,
    page,
    pageSize: query.pageSize,
    pageCount,
    summary: computeSuppliersSummary(filtered),
    facets: { categories, regions, operationalStatuses },
  };
}

export function countActiveSupplierFilters(query: SuppliersQuery): number {
  let n = 0;
  if (query.q) n += 1;
  if (query.category !== "all") n += 1;
  if (query.operationalStatus !== "all") n += 1;
  if (query.integrationStatus !== "all") n += 1;
  if (query.credentialStatus !== "all") n += 1;
  if (query.settlementStatus !== "all") n += 1;
  if (query.operatingRegion) n += 1;
  if (query.hasOutstandingSettlement !== "all") n += 1;
  if (query.activityFrom || query.activityTo) n += 1;
  return n;
}
