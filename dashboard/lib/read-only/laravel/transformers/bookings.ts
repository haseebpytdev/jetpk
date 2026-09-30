import type {
  BookingDetail,
  BookingRecord,
  BookingsPageResult,
  TripType,
} from "@/types/booking";
import type { LaravelBookingsListPayload } from "@/lib/read-only/laravel/types";

function mapTripType(raw: unknown): TripType {
  const value = String(raw ?? "").toLowerCase();
  if (value === "return" || value === "round_trip" || value === "roundtrip") {
    return "return";
  }
  return "one_way";
}

function normalizeBookingRecord(row: BookingRecord): BookingRecord {
  return {
    ...row,
    tripType: mapTripType(row.tripType),
    returnDate: row.returnDate ?? null,
  };
}

export function transformBookingsPage(
  payload: LaravelBookingsListPayload,
  pagination: {
    page: number;
    pageSize: number;
    total: number;
    pageCount: number;
  },
): BookingsPageResult {
  const bookings = (payload.bookings as BookingRecord[]).map(normalizeBookingRecord);
  return {
    bookings,
    total: pagination.total,
    page: pagination.page,
    pageSize: pagination.pageSize,
    pageCount: pagination.pageCount,
    summary: payload.summary,
    facets: payload.facets,
  };
}

export function transformBookingDetail(
  payload: { summary: BookingRecord } | BookingRecord,
): BookingRecord {
  if ("summary" in payload && payload.summary) {
    return normalizeBookingRecord(payload.summary);
  }
  return normalizeBookingRecord(payload as BookingRecord);
}

type LaravelBookingDetailPayload = {
  summary: BookingRecord;
  itinerary?: BookingDetail["itinerary"];
  passengers?: BookingDetail["passengers"];
  fareSummary?: BookingDetail["fareSummary"];
  paymentSummary?: BookingDetail["paymentSummary"];
  pnrSummary?: BookingDetail["pnrSummary"];
  ticketReadiness?: BookingDetail["ticketReadiness"];
  auditMetadata?: BookingDetail["auditMetadata"];
};

export function transformBookingManagementDetail(
  payload: LaravelBookingDetailPayload | BookingRecord,
): BookingDetail {
  if (!("summary" in payload) || !payload.summary) {
    const summary = normalizeBookingRecord(payload as BookingRecord);
    return {
      summary,
      itinerary: {
        route: `${summary.origin} → ${summary.destination}`,
        airline: summary.airline,
        travelDate: summary.departureDate || null,
        returnDate: summary.returnDate,
      },
      passengers: [],
      fareSummary: null,
      paymentSummary: null,
      pnrSummary: null,
      ticketReadiness: null,
      auditMetadata: null,
    };
  }

  const summary = normalizeBookingRecord(payload.summary);
  const itinerary = payload.itinerary ?? {
    route: `${summary.origin} → ${summary.destination}`,
    airline: summary.airline,
    travelDate: summary.departureDate || null,
    returnDate: summary.returnDate,
  };

  return {
    summary,
    itinerary: {
      ...itinerary,
      returnDate: itinerary.returnDate ?? summary.returnDate ?? null,
    },
    passengers: payload.passengers ?? [],
    fareSummary: payload.fareSummary ?? null,
    paymentSummary: payload.paymentSummary ?? null,
    pnrSummary: payload.pnrSummary ?? null,
    ticketReadiness: payload.ticketReadiness ?? null,
    auditMetadata: payload.auditMetadata ?? null,
  };
}
