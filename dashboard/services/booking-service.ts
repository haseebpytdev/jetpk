import type { BookingsPageResult, BookingsQuery, BookingRecord, BookingManagementDetail } from "@/types/booking";
import { buildBookingsPage } from "@/lib/bookings-filter";
import { buildBookingManagementFixture, getBookingById, mockBookings } from "@/mocks/booking-fixtures";
import { createReadOnlyEnvelope } from "@/lib/read-only/response-envelope";
import { createReadOnlyService, ReadOnlyServiceError, type ReadOnlyFetchOptions } from "@/lib/read-only/read-only-service";
import { fetchDashboardApi } from "@/lib/read-only/laravel/laravel-client";
import { DASHBOARD_API_ROUTES } from "@/lib/read-only/laravel/api-base";
import {
  transformBookingDetail,
  transformBookingManagementDetail,
  transformBookingsPage,
} from "@/lib/read-only/laravel/transformers/bookings";
import type { LaravelBookingsListPayload } from "@/lib/read-only/laravel/types";

export class BookingsServiceError extends Error {
  readonly referenceId: string;

  constructor(message: string, referenceId: string) {
    super(message);
    this.name = "BookingsServiceError";
    this.referenceId = referenceId;
  }
}

function mapReadOnlyError(error: unknown): never {
  if (error instanceof ReadOnlyServiceError) {
    throw new BookingsServiceError(error.envelope.error.message, error.envelope.error.referenceIdSafe);
  }
  throw error;
}

function toLaravelQuery(query: BookingsQuery): Record<string, string | number> {
  return {
    page: query.page,
    pageSize: query.pageSize,
    q: query.q,
    status: query.status,
    payment: query.payment,
    supplier: query.supplier,
    bookingDateFrom: query.bookingDateFrom,
    bookingDateTo: query.bookingDateTo,
    departureDateFrom: query.departureDateFrom,
    departureDateTo: query.departureDateTo,
    sort: query.sort,
    direction: query.direction,
  };
}

const bookingsService = createReadOnlyService<BookingsQuery, BookingsPageResult>({
  module: "bookings",
  fixtureAdapter: {
    mode: "fixture",
    async fetch(query, options) {
      if (query.previewError) {
        throw new ReadOnlyServiceError({
          error: {
            code: "internal_error",
            message: "Mock booking service returned a recoverable error (preview simulation).",
            referenceIdSafe: "BK-PREVIEW-SIM-ERR",
          },
          meta: { source: "fixture", schemaVersion: "dash-read-only-v1" },
        });
      }
      await new Promise((r) => setTimeout(r, 80));
      return createReadOnlyEnvelope({ data: buildBookingsPage(query, mockBookings), metadata: options?.metadata });
    },
  },
  laravelAdapter: {
    mode: "laravelReadOnly",
    async fetch(query, options) {
      const envelope = await fetchDashboardApi<LaravelBookingsListPayload>(DASHBOARD_API_ROUTES.bookings, {
        signal: options?.signal,
        query: toLaravelQuery(query),
      });
      const pagination = envelope.pagination ?? { page: 1, pageSize: 25, total: 0, pageCount: 1 };
      return { ...envelope, data: transformBookingsPage(envelope.data, pagination) };
    },
  },
});

export async function getBookingsPage(query: BookingsQuery, options?: ReadOnlyFetchOptions): Promise<BookingsPageResult> {
  try {
    const envelope = await bookingsService.fetchReadOnly(query, options);
    return envelope.data;
  } catch (error) {
    mapReadOnlyError(error);
  }
}

export async function getBookingDetail(id: string, options?: ReadOnlyFetchOptions): Promise<BookingRecord | null> {
  const detail = await getBookingManagementDetail(id, options);
  return detail?.summary ?? null;
}

export async function getBookingManagementDetail(
  id: string,
  options?: ReadOnlyFetchOptions,
): Promise<BookingManagementDetail | null> {
  const { resolveDataSourceMode } = await import("@/lib/read-only/data-source");
  const mode = resolveDataSourceMode();

  if (mode === "fixture") {
    await new Promise((r) => setTimeout(r, 40));
    const summary = getBookingById(id);
    if (!summary) {
      return null;
    }
    return buildBookingManagementFixture(summary);
  }

  try {
    const envelope = await fetchDashboardApi<BookingManagementDetail>(
      DASHBOARD_API_ROUTES.bookingDetail(id),
      { signal: options?.signal },
    );
    return transformBookingManagementDetail(envelope.data);
  } catch (error) {
    if (error instanceof ReadOnlyServiceError && error.envelope.error.code === "not_found") {
      return null;
    }
    mapReadOnlyError(error);
  }
}

export function listAllMockBookings(): BookingRecord[] {
  return mockBookings;
}
