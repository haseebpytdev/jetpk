import type { BookingRecord, BookingsPageResult } from "@/types/booking";
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
