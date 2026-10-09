import type { PnrRecord } from "@/types/pnr";

export function pnrHasLinkedBooking(pnr: PnrRecord): boolean {
  const value = pnr.bookingId.trim();
  if (value === "") {
    return false;
  }
  const lowered = value.toLowerCase();
  return lowered !== "—" && lowered !== "-" && lowered !== "n/a" && lowered !== "none";
}

export function pnrViewBookingPath(pnr: PnrRecord): string {
  return `/bookings/${encodeURIComponent(pnr.bookingId.trim())}?from=pnr&pnrRef=${encodeURIComponent(pnr.id)}`;
}

export function pnrStandaloneDetailPath(pnr: PnrRecord): string {
  return `/pnrs/${encodeURIComponent(pnr.id)}`;
}
