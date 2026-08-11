import type { BookingManagementDetail, BookingRecord, BookingsPageResult } from "@/types/booking";
import type { LaravelBookingsListPayload } from "@/lib/read-only/laravel/types";

export function transformBookingsPage(payload: LaravelBookingsListPayload, pagination: {
  page: number;
  pageSize: number;
  total: number;
  pageCount: number;
}): BookingsPageResult {
  return {
    bookings: payload.bookings as BookingRecord[],
    total: pagination.total,
    page: pagination.page,
    pageSize: pagination.pageSize,
    pageCount: pagination.pageCount,
    summary: payload.summary,
    facets: payload.facets,
  };
}

export function transformBookingDetail(payload: { summary: BookingRecord } | BookingRecord): BookingRecord {
  if ("summary" in payload && payload.summary) {
    return payload.summary;
  }
  return payload as BookingRecord;
}

export function transformBookingManagementDetail(
  payload: BookingManagementDetail | { summary: BookingRecord } | BookingRecord,
): BookingManagementDetail {
  if ("summary" in payload && payload.summary && "passengers" in payload) {
    return payload as BookingManagementDetail;
  }
  const summary = transformBookingDetail(payload as { summary: BookingRecord } | BookingRecord);
  return {
    summary,
    passengers: [],
    fareSummary: {
      currency: summary.currency,
      currencyStatus: summary.currencyStatus,
      currencySource: summary.currencySource,
      baseFare: summary.totalAmount,
      taxes: 0,
      fees: 0,
      markup: 0,
      total: summary.totalAmount,
    },
    pnrSummary: {
      pnr: summary.pnr || null,
      supplierReference: summary.supplierReference,
      supplier: summary.supplier,
      supplierStatus: "unknown",
    },
    ticketReadiness: {
      ticketingStatus: summary.ticketingStatus,
      ticketCount: 0,
    },
    auditMetadata: {
      createdAt: summary.bookingDate,
      updatedAt: summary.lastUpdated,
      bookingStatus: summary.bookingStatus,
    },
    statusTimeline: [],
    internalNotes: [],
    communications: [],
    documents: [],
  };
}
