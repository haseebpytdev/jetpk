import type {
  ContactFormValues,
  PassengerFormValues,
  StandardPassengersContext,
} from "../types";

const TITLES = ["Mr", "Mrs", "Ms", "Miss", "Dr", "Mstr"] as const;
const ADULT_TITLES = new Set<string>(["Mr", "Mrs", "Ms", "Miss", "Dr"]);
const CHILD_TITLES = new Set<string>(["Mstr", "Miss", "Ms", "Mr"]);

const NULLISH = new Set(["", "null", "undefined", "none", "nil"]);

function isNullish(value: unknown): boolean {
  if (value === null || value === undefined) return true;
  if (typeof value !== "string") return false;
  return NULLISH.has(value.trim().toLowerCase());
}

function normalizeGender(raw: unknown, passengerType: string): "male" | "female" {
  if (typeof raw === "string") {
    const g = raw.trim().toLowerCase();
    if (g === "male" || g === "m") return "male";
    if (g === "female" || g === "f") return "female";
  }
  // Safe default — never leave controlled selects on null.
  return "male";
}

/**
 * Resolve a valid title for hydrated / default passenger state.
 * Never invent Mrs/Miss from gender alone.
 */
export function normalizePassengerTitle(
  raw: unknown,
  passengerType: string,
  gender: "male" | "female",
): string {
  const type = (passengerType || "adult").toLowerCase();
  if (typeof raw === "string" && !isNullish(raw)) {
    const trimmed = raw.trim();
    const match = TITLES.find((t) => t.toLowerCase() === trimmed.toLowerCase());
    if (match) {
      if (type === "infant" || type === "child") {
        if (CHILD_TITLES.has(match)) return match;
      } else if (ADULT_TITLES.has(match)) {
        return match;
      }
    }
  }

  if (type === "infant" || type === "child") {
    // Do not force adult Mr. Use Mstr / Ms — never invent Mrs/Miss.
    return gender === "female" ? "Ms" : "Mstr";
  }

  // Adult: Mr for male; Ms (never Mrs/Miss) when female / neutral automatic.
  return gender === "female" ? "Ms" : "Mr";
}

export function emptyPassenger(type: string): PassengerFormValues {
  const passengerType = type || "adult";
  const gender = normalizeGender(undefined, passengerType);
  return {
    passenger_type: passengerType,
    title: normalizePassengerTitle(undefined, passengerType, gender),
    first_name: "",
    last_name: "",
    gender,
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

function hydrateString(raw: unknown): string {
  if (isNullish(raw)) return "";
  return String(raw).trim();
}

/**
 * Merge persisted/API passenger values over defaults without letting
 * null/"null"/undefined wipe valid title/gender defaults.
 */
export function normalizeHydratedPassenger(
  slotType: string,
  existing: Partial<PassengerFormValues> | Record<string, unknown> | null | undefined,
): PassengerFormValues {
  const defaults = emptyPassenger(slotType);
  const src = existing ?? {};

  const gender: "male" | "female" = isNullish((src as { gender?: unknown }).gender)
    ? normalizeGender(undefined, slotType)
    : normalizeGender((src as { gender?: unknown }).gender, slotType);

  const title = normalizePassengerTitle(
    (src as { title?: unknown }).title,
    slotType,
    gender,
  );

  return {
    passenger_type: slotType,
    title,
    first_name: hydrateString((src as { first_name?: unknown }).first_name) || defaults.first_name,
    last_name: hydrateString((src as { last_name?: unknown }).last_name) || defaults.last_name,
    gender,
    date_of_birth: hydrateString((src as { date_of_birth?: unknown }).date_of_birth),
    nationality: hydrateString((src as { nationality?: unknown }).nationality),
    document_type:
      hydrateString((src as { document_type?: unknown }).document_type) || defaults.document_type,
    passport_number: hydrateString((src as { passport_number?: unknown }).passport_number),
    passport_issuing_country: hydrateString(
      (src as { passport_issuing_country?: unknown }).passport_issuing_country,
    ),
    passport_expiry_date: hydrateString((src as { passport_expiry_date?: unknown }).passport_expiry_date),
    passport_issue_date: hydrateString((src as { passport_issue_date?: unknown }).passport_issue_date),
    national_id_number: hydrateString((src as { national_id_number?: unknown }).national_id_number),
  };
}

export function buildPassengersFromContext(context: StandardPassengersContext): PassengerFormValues[] {
  const expected = context.travellers.expected ?? [];
  const existing = context.existing_values.passengers ?? [];

  return expected.map((slot, index) =>
    normalizeHydratedPassenger(slot.type, existing[index] as Partial<PassengerFormValues> | undefined),
  );
}

export function buildContactFromContext(context: StandardPassengersContext): ContactFormValues {
  const existing = context.existing_values.contact ?? {};
  const defaults = emptyContact();
  return {
    ...defaults,
    contact_name: hydrateString(existing.contact_name) || defaults.contact_name,
    email: hydrateString(existing.email) || defaults.email,
    phone: hydrateString(existing.phone) || defaults.phone,
    phone_country_code: hydrateString(existing.phone_country_code) || defaults.phone_country_code,
    phone_number: hydrateString(existing.phone_number) || defaults.phone_number,
    country: hydrateString(existing.country) || defaults.country,
  };
}

export function passengerLabel(slot: { type: string; label: string }, ordinal: number): string {
  return `${slot.label} ${ordinal}`;
}

function appendFormValue(formData: FormData, key: string, value: unknown): void {
  if (value === null || value === undefined) return;
  if (typeof value === "boolean") {
    if (value) formData.set(key, "1");
    return;
  }
  if (typeof value === "number") {
    if (!Number.isFinite(value)) return;
    formData.set(key, String(value));
    return;
  }
  const asString = String(value);
  if (asString === "" || NULLISH.has(asString.trim().toLowerCase())) return;
  formData.set(key, asString);
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
    const normalized = normalizeHydratedPassenger(passenger.passenger_type || "adult", passenger);
    // Required enums always present as concrete values — never null/"null".
    formData.set(`passengers[${index}][passenger_type]`, normalized.passenger_type);
    formData.set(`passengers[${index}][title]`, normalized.title);
    formData.set(`passengers[${index}][gender]`, normalized.gender);
    formData.set(`passengers[${index}][document_type]`, normalized.document_type || "passport");

    (Object.keys(normalized) as Array<keyof PassengerFormValues>).forEach((key) => {
      if (key === "passenger_type" || key === "title" || key === "gender" || key === "document_type") {
        return;
      }
      appendFormValue(formData, `passengers[${index}][${key}]`, normalized[key]);
    });
  });

  appendFormValue(formData, "email", contact.email);
  appendFormValue(
    formData,
    "phone",
    contact.phone || `${contact.phone_country_code}${contact.phone_number}`,
  );
  appendFormValue(formData, "phone_country_code", contact.phone_country_code);
  appendFormValue(formData, "phone_number", contact.phone_number);
  appendFormValue(formData, "contact_name", contact.contact_name);
  appendFormValue(formData, "country", contact.country);
  if (contact.create_account) formData.set("create_account", "1");
  appendFormValue(formData, "password", contact.password);
  appendFormValue(formData, "password_confirmation", contact.password_confirmation);

  const termsVersion = context.consent?.terms_version ?? "jetpk-checkout-terms-2026-08-22";
  formData.set("terms_version", termsVersion);
  if (options?.termsAccepted) {
    formData.set("terms_accepted", "1");
  }

  return formData;
}

export { TITLES };
