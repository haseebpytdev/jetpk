/**
 * Future seat-selection contract boundary (readiness only — no operational seat map in JP-FE-07).
 */
export type SeatMapResponse = {
  search_id?: string;
  offer_id?: string;
  booking_id?: string;
  group_booking_id?: string;
  segments: SeatMapSegment[];
  seat_map_version?: string;
  expires_at?: string;
};

export type SeatMapSegment = {
  segment_id: string;
  rows: SeatMapRow[];
};

export type SeatMapRow = {
  row_number: string;
  seats: Seat[];
};

export type Seat = {
  seat_number: string;
  seat_status: "available" | "occupied" | "blocked" | "unknown";
  seat_price?: number;
  currency?: string;
  characteristics?: SeatCharacteristic[];
  selection_token?: string;
};

export type SeatCharacteristic = {
  code: string;
  label: string;
};

export type PassengerSeatSelection = {
  passenger_id: string;
  segment_id: string;
  seat_number: string;
  seat_price?: number;
  currency?: string;
  selection_token?: string;
};

export type SeatSelectionRequest = {
  selections: PassengerSeatSelection[];
};

export type SeatSelectionResponse = {
  success: boolean;
  status: string;
  selections?: PassengerSeatSelection[];
  expires_at?: string;
};

export type SeatSelectionStatus = "unavailable" | "pending" | "confirmed" | "expired";
