import { mrzCheckDigit } from "./parseMrz";

/**
 * Synthetic ICAO-style TD3 fixtures only. No real passport PII.
 * Names/numbers follow Doc 9303 example patterns (UTO / L898902C3).
 */

function withCheckDigit(data: string): string {
  return `${data}${mrzCheckDigit(data)}`;
}

function buildLine2(parts: {
  documentNumber: string; // 9 chars padded with <
  nationality: string; // 3
  dob: string; // YYMMDD
  sex: string; // 1
  expiry: string; // YYMMDD
  optional?: string; // 14 chars
}): string {
  const doc = parts.documentNumber.padEnd(9, "<").slice(0, 9);
  const docCd = String(mrzCheckDigit(doc));
  const dobCd = String(mrzCheckDigit(parts.dob));
  const expiryCd = String(mrzCheckDigit(parts.expiry));
  const optional = (parts.optional ?? "").padEnd(14, "<").slice(0, 14);
  const optionalCd = String(mrzCheckDigit(optional));
  const composite = `${doc}${docCd}${parts.dob}${dobCd}${parts.expiry}${expiryCd}${optional}${optionalCd}`;
  const compositeCd = String(mrzCheckDigit(composite));
  return `${doc}${docCd}${parts.nationality}${parts.dob}${dobCd}${parts.sex}${parts.expiry}${expiryCd}${optional}${optionalCd}${compositeCd}`;
}

/** Valid ICAO Doc 9303 sample-shaped passport MRZ (synthetic). */
export const SYNTHETIC_VALID_MRZ = [
  "P<UTOERIKSSON<<ANNA<MARIA<<<<<<<<<<<<<<<<<<<",
  buildLine2({
    documentNumber: "L898902C3",
    nationality: "UTO",
    dob: "740812",
    sex: "F",
    expiry: "120415",
    optional: "ZE184226B<<<<<",
  }),
].join("\n");

/** Same person with a corrupted passport-number check digit. */
export const SYNTHETIC_INVALID_CHECK_DIGIT_MRZ = [
  "P<UTOERIKSSON<<ANNA<MARIA<<<<<<<<<<<<<<<<<<<",
  (() => {
    const valid = buildLine2({
      documentNumber: "L898902C3",
      nationality: "UTO",
      dob: "740812",
      sex: "F",
      expiry: "120415",
      optional: "ZE184226B<<<<<",
    });
    // Flip the passport number check digit (position 10 / index 9).
    const bad = valid[9] === "0" ? "1" : "0";
    return `${valid.slice(0, 9)}${bad}${valid.slice(10)}`;
  })(),
].join("\n");

/** Only line 1 present — partial extraction. */
export const SYNTHETIC_PARTIAL_MRZ = "P<UTOERIKSSON<<ANNA<MARIA<<<<<<<<<<<<<<<<<<<";

/** Valid MRZ with future expiry for booking form tests (still synthetic). */
export const SYNTHETIC_VALID_MRZ_FUTURE_EXPIRY = [
  "P<UTOSAMPLE<<TRAVELER<<<<<<<<<<<<<<<<<<<<<<<",
  buildLine2({
    documentNumber: "X1234567<",
    nationality: "UTO",
    dob: "900101",
    sex: "M",
    expiry: "301231",
    optional: "<<<<<<<<<<<<<<",
  }),
].join("\n");

export const SYNTHETIC_MRZ_FIXTURES = {
  valid: SYNTHETIC_VALID_MRZ,
  invalidCheckDigit: SYNTHETIC_INVALID_CHECK_DIGIT_MRZ,
  partial: SYNTHETIC_PARTIAL_MRZ,
  validFutureExpiry: SYNTHETIC_VALID_MRZ_FUTURE_EXPIRY,
} as const;

/** Exported helper for tests that assert check-digit construction. */
export { withCheckDigit, buildLine2 };
