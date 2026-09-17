import type { PassengerFormValues } from "../types";

/**
 * Safe title assistance from passport sex / explicit title.
 * MRZ never encodes marital status — never infer Mrs vs Miss.
 */
export function suggestTitleFromPassport(input: {
  gender?: string | null;
  explicitTitle?: string | null;
  msSupported?: boolean;
}): string | null {
  const explicit = (input.explicitTitle ?? "").trim();
  if (explicit) {
    const normalized = explicit.replace(/\./g, "");
    const match = ["Mr", "Mrs", "Ms", "Miss", "Dr", "Mstr"].find(
      (title) => title.toLowerCase() === normalized.toLowerCase(),
    );
    return match ?? null;
  }

  const gender = (input.gender ?? "").trim().toLowerCase();
  if (gender === "male" || gender === "m") {
    return "Mr";
  }
  if (gender === "female" || gender === "f") {
    // Neutral automatic title when Ms is supported end-to-end.
    return input.msSupported === false ? null : "Ms";
  }

  return null;
}

/**
 * Apply title only when the passenger title field is empty / default-unset.
 * Never overwrite an explicit customer choice.
 */
export function applyTitleAssistance(
  passenger: PassengerFormValues,
  genderOrTitle: { gender?: string | null; explicitTitle?: string | null },
): PassengerFormValues {
  if (passenger.title && passenger.title.trim() !== "") {
    // Treat form default "Mr" with empty names as unset when gender extraction is female.
    const looksLikeUntouchedDefault =
      passenger.title === "Mr" &&
      !passenger.first_name.trim() &&
      !passenger.last_name.trim() &&
      (genderOrTitle.gender ?? "").toLowerCase().startsWith("f");
    if (!looksLikeUntouchedDefault) {
      return passenger;
    }
  }

  const suggested = suggestTitleFromPassport({
    gender: genderOrTitle.gender ?? passenger.gender,
    explicitTitle: genderOrTitle.explicitTitle,
    msSupported: true,
  });

  if (!suggested) {
    return passenger;
  }

  return { ...passenger, title: suggested };
}
