import type {
  ContactFormValues,
  PassengerFormValues,
  StandardPassengersContext,
} from "../types";

const TITLES = ["Mr", "Mrs", "Ms", "Miss", "Dr", "Mstr"] as const;

export function emptyPassenger(type: string): PassengerFormValues {
  return {
    passenger_type: type,
    title: "Mr",
    first_name: "",
    last_name: "",
    gender: "male",
    date_of_birth: "",
    nationality: "",
    document_type: "passport",
    passport_number: "",
    passport_issuing_country: "",
    passport_expiry_date: "",
    passport_issue_date: "",
    national_id_number: "",
  };
}

export function emptyContact(): ContactFormValues {
  return {
    contact_name: "",
    email: "",
    phone: "",
    phone_country_code: "+92",
    phone_number: "",
    country: "Pakistan",
    create_account: false,
    password: "",
    password_confirmation: "",
  };
}

export function buildPassengersFromContext(context: StandardPassengersContext): PassengerFormValues[] {
  const expected = context.travellers.expected ?? [];
  const existing = context.existing_values.passengers ?? [];

  return expected.map((slot, index) => ({
    ...emptyPassenger(slot.type),
    ...(existing[index] ?? {}),
    passenger_type: slot.type,
  }));
}

export function buildContactFromContext(context: StandardPassengersContext): ContactFormValues {
  return {
    ...emptyContact(),
    ...context.existing_values.contact,
  };
}

export function passengerLabel(slot: { type: string; label: string }, ordinal: number): string {
  return `${slot.label} ${ordinal}`;
}

export function buildPassengerFormData(
  context: StandardPassengersContext,
  passengers: PassengerFormValues[],
  contact: ContactFormValues,
  options?: { termsAccepted?: boolean },
): FormData {
  const formData = new FormData();
  const selection = context.selection;

  formData.set("search_id", selection.search_id);
  formData.set("offer_id", selection.offer_id);
  formData.set("flight_id", selection.offer_id);
  if (selection.fare_option_key) formData.set("fare_option_key", selection.fare_option_key);
  if (selection.return_fare_option_key) formData.set("return_fare_option_key", selection.return_fare_option_key);
  if (selection.outbound_fare_option_key) formData.set("outbound_fare_option_key", selection.outbound_fare_option_key);
  if (selection.outbound_key) formData.set("outbound_key", selection.outbound_key);
  if (selection.combo_id) formData.set("combo_id", selection.combo_id);
  formData.set("from", selection.from);
  formData.set("to", selection.to);
  formData.set("depart", selection.depart);
  formData.set("trip_type", selection.trip_type);
  if (selection.return_date) formData.set("return_date", selection.return_date);
  formData.set("cabin", selection.cabin);
  formData.set("adults", String(context.travellers.adults));
  formData.set("children", String(context.travellers.children));
  formData.set("infants", String(context.travellers.infants));
  formData.set("lead_passenger_index", String(context.travellers.lead_passenger_index));

  passengers.forEach((passenger, index) => {
    Object.entries(passenger).forEach(([key, value]) => {
      if (value !== undefined && value !== "") {
        formData.set(`passengers[${index}][${key}]`, value);
      }
    });
  });

  formData.set("email", contact.email);
  formData.set("phone", contact.phone || `${contact.phone_country_code}${contact.phone_number}`);
  if (contact.phone_country_code) formData.set("phone_country_code", contact.phone_country_code);
  if (contact.phone_number) formData.set("phone_number", contact.phone_number);
  if (contact.contact_name) formData.set("contact_name", contact.contact_name);
  if (contact.country) formData.set("country", contact.country);
  if (contact.create_account) formData.set("create_account", "1");
  if (contact.password) formData.set("password", contact.password);
  if (contact.password_confirmation) formData.set("password_confirmation", contact.password_confirmation);

  const termsVersion = context.consent?.terms_version ?? "jetpk-checkout-terms-2026-08-22";
  formData.set("terms_version", termsVersion);
  if (options?.termsAccepted) {
    formData.set("terms_accepted", "1");
  }

  return formData;
}

export { TITLES };
