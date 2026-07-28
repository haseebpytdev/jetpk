import type { FlightSegment, GroupSearchDraft, PassengerSelection, SearchDraft, SearchMode } from "../types";

export type ValidationResult = {
  valid: boolean;
  errors: string[];
};

function validatePassengers(passengers: PassengerSelection): string[] {
  const errors: string[] = [];
  if (passengers.adults < 1) errors.push("At least one adult is required.");
  if (passengers.infants > passengers.adults) errors.push("Infants cannot exceed adults.");
  if (passengers.children < 0 || passengers.infants < 0) errors.push("Passenger counts must be positive.");
  return errors;
}

function validateSegment(segment: FlightSegment, index: number): string[] {
  const errors: string[] = [];
  const label = `Segment ${index + 1}`;
  if (!segment.from) errors.push(`${label}: origin is required.`);
  if (!segment.to) errors.push(`${label}: destination is required.`);
  if (segment.from && segment.to && segment.from.iata === segment.to.iata) {
    errors.push(`${label}: origin and destination must differ.`);
  }
  if (!segment.departureDate) errors.push(`${label}: departure date is required.`);
  return errors;
}

export function validateFlightSearch(
  mode: Exclude<SearchMode, "group">,
  segments: FlightSegment[],
  passengers: PassengerSelection,
  returnDate?: string,
): ValidationResult {
  const errors = [...validatePassengers(passengers)];

  if (segments.length === 0) {
    errors.push("At least one flight segment is required.");
    return { valid: false, errors };
  }

  segments.forEach((segment, index) => {
    errors.push(...validateSegment(segment, index));
  });

  if (mode === "return") {
    if (!returnDate) errors.push("Return date is required.");
    const outbound = segments[0];
    if (outbound?.departureDate && returnDate && returnDate < outbound.departureDate) {
      errors.push("Return date cannot be before departure date.");
    }
  }

  if (mode === "multi_city" && segments.length < 2) {
    errors.push("Multi-city search requires at least two segments.");
  }

  return { valid: errors.length === 0, errors };
}

export function validateGroupSearch(draft: Omit<GroupSearchDraft, "submittedAt">): ValidationResult {
  const errors: string[] = [];
  if (!draft.sector) errors.push("Sector is required.");
  if (!draft.travelDate) errors.push("Travel date is required.");
  if (!draft.category) errors.push("Category is required.");
  return { valid: errors.length === 0, errors };
}

export function buildSearchDraft(
  mode: Exclude<SearchMode, "group">,
  segments: FlightSegment[],
  passengers: PassengerSelection,
  options: SearchDraft["options"],
): SearchDraft {
  return {
    mode,
    segments,
    passengers,
    options,
    submittedAt: new Date().toISOString(),
  };
}

export function buildGroupSearchDraft(
  draft: Omit<GroupSearchDraft, "submittedAt">,
): GroupSearchDraft {
  return {
    ...draft,
    submittedAt: new Date().toISOString(),
  };
}
