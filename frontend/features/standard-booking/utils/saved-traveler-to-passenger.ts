import type { PassengerFormValues } from "../types";
import type { CheckoutSavedTravelerFill } from "../services/standard-booking-api";

export function savedTravelerToPassenger(
  traveler: CheckoutSavedTravelerFill,
  passengerType: PassengerFormValues["passenger_type"],
): PassengerFormValues {
  const documentType = (traveler.document_type || "passport").toLowerCase();
  const documentNumber = traveler.document_number ?? "";

  return {
    passenger_type: passengerType,
    title: traveler.title || "Mr",
    first_name: traveler.first_name || "",
    last_name: traveler.last_name || "",
    gender: (traveler.gender as PassengerFormValues["gender"]) || "male",
    date_of_birth: traveler.date_of_birth || "",
    nationality: traveler.nationality || "PK",
    document_type: documentType === "national_id" ? "national_id" : "passport",
    passport_number: documentType === "national_id" ? "" : documentNumber,
    national_id_number: documentType === "national_id" ? documentNumber : "",
    passport_expiry_date: traveler.document_expiry || "",
    passport_issue_date: "",
    passport_issuing_country: traveler.issuing_country || "PK",
  };
}

export function passengerSlotLooksEmpty(passenger: PassengerFormValues): boolean {
  return (
    !passenger.first_name?.trim() &&
    !passenger.last_name?.trim() &&
    !passenger.passport_number?.trim() &&
    !passenger.national_id_number?.trim()
  );
}
