import assert from "node:assert/strict";
import test from "node:test";
import {
  alpha3ToAlpha2,
  mrzCheckDigit,
  mrzDateToIso,
  parseTd3Mrz,
  verifyCheckDigit,
} from "../../features/standard-booking/document-reader/mrz/parseMrz";
import {
  SYNTHETIC_INVALID_CHECK_DIGIT_MRZ,
  SYNTHETIC_PARTIAL_MRZ,
  SYNTHETIC_VALID_MRZ,
  SYNTHETIC_VALID_MRZ_FUTURE_EXPIRY,
  buildLine2,
} from "../../features/standard-booking/document-reader/mrz/fixtures";
import {
  applyConfirmedExtraction,
  planExtractedFieldMerge,
} from "../../features/standard-booking/document-reader/applyExtractedFields";
import type { PassengerFormValues } from "../../features/standard-booking/types";
import {
  applyTitleAssistance,
  suggestTitleFromPassport,
} from "../../features/standard-booking/document-reader/titleFromPassport";

const emptyPassenger: PassengerFormValues = {
  passenger_type: "adult",
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

test("valid synthetic MRZ extracts core fields and validates check digits", () => {
  const result = parseTd3Mrz(SYNTHETIC_VALID_MRZ);
  assert.equal(result.ok, true);
  assert.equal(result.checkDigitsValid, true);
  assert.equal(result.fields.last_name, "ERIKSSON");
  assert.equal(result.fields.first_name, "ANNA MARIA");
  assert.equal(result.fields.passport_number, "L898902C3");
  assert.equal(result.fields.nationality, "UT");
  assert.equal(result.fields.gender, "female");
  assert.equal(result.fields.date_of_birth, "1974-08-12");
  assert.equal(result.fields.passport_expiry_date, "2012-04-15");
  assert.equal(result.fields.passport_issuing_country, "UT");
  assert.equal(result.fields.passport_issue_date, undefined);
});

test("invalid check digit is flagged and never invents issue date", () => {
  const result = parseTd3Mrz(SYNTHETIC_INVALID_CHECK_DIGIT_MRZ);
  assert.equal(result.checkDigitsValid, false);
  assert.match(result.warnings.join(" "), /check digit/i);
  assert.equal(result.fields.passport_issue_date, undefined);
  assert.ok(result.fields.passport_number);
});

test("partial MRZ extraction returns warnings without inventing fields", () => {
  const result = parseTd3Mrz(SYNTHETIC_PARTIAL_MRZ);
  assert.equal(result.ok, false);
  assert.equal(result.checkDigitsValid, false);
  assert.ok(result.warnings.length > 0);
  assert.equal(result.fields.passport_number, undefined);
});

test("mrz check digit helper matches ICAO weights", () => {
  assert.equal(mrzCheckDigit("L898902C3"), 6);
  assert.equal(verifyCheckDigit("740812", "2"), true);
  assert.equal(verifyCheckDigit("120415", "9"), true);
});

test("alpha3 and date helpers", () => {
  assert.equal(alpha3ToAlpha2("PAK"), "PK");
  assert.equal(alpha3ToAlpha2("UTO"), "UT");
  assert.equal(mrzDateToIso("900101", "dob"), "1990-01-01");
  assert.equal(mrzDateToIso("301231", "expiry"), "2030-12-31");
});

test("confirmation applies extracted fields to empty passenger", () => {
  const parsed = parseTd3Mrz(SYNTHETIC_VALID_MRZ_FUTURE_EXPIRY);
  assert.equal(parsed.ok, true);
  const next = applyConfirmedExtraction(emptyPassenger, parsed.fields);
  assert.equal(next.last_name, "SAMPLE");
  assert.equal(next.first_name, "TRAVELER");
  assert.equal(next.passport_number, "X1234567");
  assert.equal(next.passport_issue_date, "");
  assert.equal(next.document_type, "passport");
});

test("male gender suggests Mr and female suggests Ms without inventing Mrs/Miss", () => {
  assert.equal(suggestTitleFromPassport({ gender: "male" }), "Mr");
  assert.equal(suggestTitleFromPassport({ gender: "female" }), "Ms");
  assert.equal(suggestTitleFromPassport({ gender: "female", msSupported: false }), null);
  assert.equal(suggestTitleFromPassport({ explicitTitle: "Dr" }), "Dr");

  const male = applyTitleAssistance({ ...emptyPassenger, title: "", gender: "male" }, { gender: "male" });
  assert.equal(male.title, "Mr");
  const female = applyTitleAssistance({ ...emptyPassenger, title: "Mr", first_name: "", last_name: "" }, { gender: "female" });
  assert.equal(female.title, "Ms");
  const kept = applyTitleAssistance({ ...emptyPassenger, title: "Miss", first_name: "A", last_name: "B" }, { gender: "female" });
  assert.equal(kept.title, "Miss");
});

test("issue date is never fabricated from expiry during merge", () => {
  const parsed = parseTd3Mrz(SYNTHETIC_VALID_MRZ_FUTURE_EXPIRY);
  assert.equal(parsed.fields.passport_issue_date, undefined);
  const next = applyConfirmedExtraction(emptyPassenger, {
    ...parsed.fields,
    // Deliberately omit issue date — merge must not invent from expiry.
  });
  assert.equal(next.passport_issue_date, "");
});

test("existing filled fields are protected until user chooses", () => {
  const existing: PassengerFormValues = {
    ...emptyPassenger,
    first_name: "TYPED",
    last_name: "PERSON",
    passport_number: "KEEPME",
  };
  const parsed = parseTd3Mrz(SYNTHETIC_VALID_MRZ);
  const plan = planExtractedFieldMerge(existing, parsed.fields);
  assert.ok(plan.conflicts.some((row) => row.field === "first_name"));
  assert.ok(plan.conflicts.some((row) => row.field === "passport_number"));
  assert.equal(plan.toApply.first_name, undefined);
  assert.equal(plan.toApply.passport_number, undefined);

  const kept = applyConfirmedExtraction(existing, parsed.fields, {
    first_name: "keep",
    last_name: "keep",
    passport_number: "keep",
  });
  assert.equal(kept.first_name, "TYPED");
  assert.equal(kept.passport_number, "KEEPME");

  const replaced = applyConfirmedExtraction(existing, parsed.fields, {
    first_name: "use_extracted",
    last_name: "use_extracted",
    passport_number: "use_extracted",
  });
  assert.equal(replaced.first_name, "ANNA MARIA");
  assert.equal(replaced.passport_number, "L898902C3");
});

test("fixture builder produces 44-char TD3 line 2", () => {
  const line2 = buildLine2({
    documentNumber: "L898902C3",
    nationality: "UTO",
    dob: "740812",
    sex: "F",
    expiry: "120415",
    optional: "ZE184226B<<<<<",
  });
  assert.equal(line2.length, 44);
  assert.equal(line2[9], "6");
});
