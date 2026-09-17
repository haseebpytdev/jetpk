import type { PassengerFormValues } from "../types";
import type { MrzExtractedFields } from "./mrz/parseMrz";

export type FieldConflict = {
  field: Exclude<keyof MrzExtractedFields, never>;
  existing: string;
  extracted: string;
};

export type MergeDecision = {
  /** Fields safe to write (empty existing or user chose extracted). */
  toApply: Partial<PassengerFormValues>;
  conflicts: FieldConflict[];
  skippedUnchanged: Array<keyof MrzExtractedFields>;
};

const FORM_FIELDS: Array<keyof MrzExtractedFields & keyof PassengerFormValues> = [
  "last_name",
  "first_name",
  "passport_number",
  "nationality",
  "date_of_birth",
  "gender",
  "passport_expiry_date",
  "passport_issuing_country",
  "passport_issue_date",
];

function isFilled(value: string | undefined): boolean {
  return Boolean(value && value.trim());
}

/**
 * Never silently overwrite typed values. Empty targets accept extraction;
 * filled targets become conflicts for explicit user confirmation.
 * Issue date is only applied when the extractor supplied a reliable value
 * (MRZ never invents it from expiry).
 */
export function planExtractedFieldMerge(
  existing: PassengerFormValues,
  extracted: Partial<MrzExtractedFields>,
  choices?: Partial<Record<keyof MrzExtractedFields, "keep" | "use_extracted">>,
): MergeDecision {
  const toApply: Partial<PassengerFormValues> = {};
  const conflicts: FieldConflict[] = [];
  const skippedUnchanged: Array<keyof MrzExtractedFields> = [];

  for (const field of FORM_FIELDS) {
    const next = extracted[field];
    if (next == null || String(next).trim() === "") continue;

    // Gender is a select default on the form; prefer MRZ sex without conflict UI.
    if (field === "gender") {
      toApply[field] = String(next);
      continue;
    }

    const current = String(existing[field] ?? "");
    if (!isFilled(current)) {
      toApply[field] = String(next);
      continue;
    }

    if (current.trim().toUpperCase() === String(next).trim().toUpperCase()) {
      skippedUnchanged.push(field);
      continue;
    }

    const choice = choices?.[field];
    if (choice === "use_extracted") {
      toApply[field] = String(next);
    } else if (choice === "keep") {
      skippedUnchanged.push(field);
    } else {
      conflicts.push({ field, existing: current, extracted: String(next) });
    }
  }

  return { toApply, conflicts, skippedUnchanged };
}

export function applyConfirmedExtraction(
  existing: PassengerFormValues,
  extracted: Partial<MrzExtractedFields>,
  choices: Partial<Record<keyof MrzExtractedFields, "keep" | "use_extracted">> = {},
): PassengerFormValues {
  const plan = planExtractedFieldMerge(existing, extracted, {
    ...Object.fromEntries(
      (Object.keys(extracted) as Array<keyof MrzExtractedFields>).map((field) => {
        const current = String(existing[field as keyof PassengerFormValues] ?? "");
        const next = extracted[field];
        if (!isFilled(current)) return [field, "use_extracted"];
        if (choices[field]) return [field, choices[field]];
        // Default unresolved conflicts to keep existing (never silent overwrite).
        if (next && current.trim().toUpperCase() !== String(next).trim().toUpperCase()) {
          return [field, "keep"];
        }
        return [field, "use_extracted"];
      }),
    ),
    ...choices,
  });

  return { ...existing, ...plan.toApply, document_type: "passport" };
}
