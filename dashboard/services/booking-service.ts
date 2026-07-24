import type { BookingsPageResult, BookingsQuery, BookingRecord } from "@/types/booking";
import { buildBookingsPage } from "@/lib/bookings-filter";
import { getBookingById, mockBookings } from "@/mocks/booking-fixtures";
import { useMockData } from "@/lib/preview";

export class BookingsServiceError extends Error {
  readonly referenceId: string;

  constructor(message: string, referenceId: string) {
    super(message);
    this.name = "BookingsServiceError";
    this.referenceId = referenceId;
  }
}

export async function getBookingsPage(query: BookingsQuery): Promise<BookingsPageResult> {
  if (!useMockData()) {
    throw new BookingsServiceError(
      "Live booking data is disabled in preview.",
      "BK-PREVIEW-NO-LIVE",
    );
  }

  if (query.previewError) {
    throw new BookingsServiceError(
      "Mock booking service returned a recoverable error (preview simulation).",
      "BK-PREVIEW-SIM-ERR",
    );
  }

  await new Promise((r) => setTimeout(r, 80));

  return buildBookingsPage(query, mockBookings);
}

export async function getBookingDetail(id: string): Promise<BookingRecord | null> {
  if (!useMockData()) {
    return null;
  }
  await new Promise((r) => setTimeout(r, 40));
  return getBookingById(id) ?? null;
}

export function listAllMockBookings(): BookingRecord[] {
  return mockBookings;
}
