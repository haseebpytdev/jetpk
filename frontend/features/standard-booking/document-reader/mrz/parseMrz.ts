/**
 * Client-side ICAO Doc 9303 TD3 (passport) MRZ parsing.
 * Architecture: CLIENT_SIDE only — never upload images to third-party OCR.
 */

export type MrzSex = "male" | "female" | "other";

export type MrzExtractedFields = {
  last_name: string;
  first_name: string;
  passport_number: string;
  nationality: string;
  date_of_birth: string;
  gender: MrzSex;
  passport_expiry_date: string;
  passport_issuing_country: string;
  /** Intentionally omitted unless reliably read from a non-MRZ field. */
  passport_issue_date?: string;
};

export type MrzFieldConfidence = {
  field: keyof MrzExtractedFields;
  confidence: "high" | "medium" | "low";
  warning?: string;
};

export type MrzParseResult = {
  ok: boolean;
  fields: Partial<MrzExtractedFields>;
  confidence: MrzFieldConfidence[];
  warnings: string[];
  checkDigitsValid: boolean;
  rawLines: string[];
};

const WEIGHTS = [7, 3, 1] as const;

/** Common ICAO alpha-3 → form alpha-2. Synthetic UTO → UT for fixtures only. */
const ALPHA3_TO_ALPHA2: Record<string, string> = {
  AFG: "AF",
  ALA: "AX",
  ALB: "AL",
  DZA: "DZ",
  ASM: "AS",
  AND: "AD",
  AGO: "AO",
  ARG: "AR",
  ARM: "AM",
  AUS: "AU",
  AUT: "AT",
  AZE: "AZ",
  BHR: "BH",
  BGD: "BD",
  BEL: "BE",
  BTN: "BT",
  BOL: "BO",
  BIH: "BA",
  BRA: "BR",
  BRN: "BN",
  BGR: "BG",
  BFA: "BF",
  KHM: "KH",
  CMR: "CM",
  CAN: "CA",
  CHL: "CL",
  CHN: "CN",
  COL: "CO",
  COD: "CD",
  COG: "CG",
  CRI: "CR",
  CIV: "CI",
  HRV: "HR",
  CUB: "CU",
  CYP: "CY",
  CZE: "CZ",
  DNK: "DK",
  DJI: "DJ",
  DOM: "DO",
  ECU: "EC",
  EGY: "EG",
  SLV: "SV",
  EST: "EE",
  ETH: "ET",
  FIN: "FI",
  FRA: "FR",
  GEO: "GE",
  DEU: "DE",
  GHA: "GH",
  GRC: "GR",
  HKG: "HK",
  HUN: "HU",
  ISL: "IS",
  IND: "IN",
  IDN: "ID",
  IRN: "IR",
  IRQ: "IQ",
  IRL: "IE",
  ISR: "IL",
  ITA: "IT",
  JAM: "JM",
  JPN: "JP",
  JOR: "JO",
  KAZ: "KZ",
  KEN: "KE",
  KWT: "KW",
  KGZ: "KG",
  LAO: "LA",
  LVA: "LV",
  LBN: "LB",
  LBY: "LY",
  LIE: "LI",
  LTU: "LT",
  LUX: "LU",
  MAC: "MO",
  MYS: "MY",
  MDV: "MV",
  MLT: "MT",
  MEX: "MX",
  MDA: "MD",
  MCO: "MC",
  MNG: "MN",
  MNE: "ME",
  MAR: "MA",
  MOZ: "MZ",
  MMR: "MM",
  NAM: "NA",
  NPL: "NP",
  NLD: "NL",
  NZL: "NZ",
  NIC: "NI",
  NER: "NE",
  NGA: "NG",
  PRK: "KP",
  MKD: "MK",
  NOR: "NO",
  OMN: "OM",
  PAK: "PK",
  PSE: "PS",
  PAN: "PA",
  PNG: "PG",
  PRY: "PY",
  PER: "PE",
  PHL: "PH",
  POL: "PL",
  PRT: "PT",
  QAT: "QA",
  ROU: "RO",
  RUS: "RU",
  RWA: "RW",
  SAU: "SA",
  SEN: "SN",
  SRB: "RS",
  SGP: "SG",
  SVK: "SK",
  SVN: "SI",
  SOM: "SO",
  ZAF: "ZA",
  KOR: "KR",
  ESP: "ES",
  LKA: "LK",
  SDN: "SD",
  SWE: "SE",
  CHE: "CH",
  SYR: "SY",
  TWN: "TW",
  TJK: "TJ",
  TZA: "TZ",
  THA: "TH",
  TLS: "TL",
  TGO: "TG",
  TTO: "TT",
  TUN: "TN",
  TUR: "TR",
  TKM: "TM",
  UGA: "UG",
  UKR: "UA",
  ARE: "AE",
  GBR: "GB",
  USA: "US",
  URY: "UY",
  UZB: "UZ",
  VEN: "VE",
  VNM: "VN",
  YEM: "YE",
  ZMB: "ZM",
  ZWE: "ZW",
  /** Synthetic ICAO Doc 9303 example issuing state — fixtures only */
  UTO: "UT",
};

function charValue(ch: string): number {
  if (ch === "<") return 0;
  if (ch >= "0" && ch <= "9") return ch.charCodeAt(0) - 48;
  if (ch >= "A" && ch <= "Z") return ch.charCodeAt(0) - 55;
  return -1;
}

export function mrzCheckDigit(data: string): number {
  let sum = 0;
  for (let i = 0; i < data.length; i += 1) {
    const value = charValue(data[i] ?? "<");
    if (value < 0) return -1;
    sum += value * WEIGHTS[i % 3];
  }
  return sum % 10;
}

export function verifyCheckDigit(data: string, expectedDigit: string): boolean {
  if (!/^[0-9]$/.test(expectedDigit)) return false;
  const computed = mrzCheckDigit(data);
  return computed >= 0 && String(computed) === expectedDigit;
}

export function alpha3ToAlpha2(code: string): string {
  const upper = code.replace(/</g, "").toUpperCase();
  if (upper.length === 2) return upper;
  return ALPHA3_TO_ALPHA2[upper] ?? upper.slice(0, 2);
}

/**
 * Convert YYMMDD MRZ date to ISO YYYY-MM-DD.
 * DOB: if YY is greater than the current two-digit year, treat as 19xx; else 20xx.
 * Expiry: treat as 20xx (passports in this product window expire in the 2000s).
 */
export function mrzDateToIso(yymmdd: string, kind: "dob" | "expiry"): string | null {
  if (!/^[0-9]{6}$/.test(yymmdd)) return null;
  const yy = Number(yymmdd.slice(0, 2));
  const mm = Number(yymmdd.slice(2, 4));
  const dd = Number(yymmdd.slice(4, 6));
  if (mm < 1 || mm > 12 || dd < 1 || dd > 31) return null;

  const currentYy = new Date().getFullYear() % 100;
  const century = kind === "dob" ? (yy > currentYy ? 1900 : 2000) : 2000;
  const year = century + yy;
  return `${String(year).padStart(4, "0")}-${String(mm).padStart(2, "0")}-${String(dd).padStart(2, "0")}`;
}

function parseNames(nameField: string): { last_name: string; first_name: string } {
  const cleaned = nameField.replace(/</g, " ").replace(/\s+/g, " ").trim();
  const parts = nameField.split("<<");
  const surname = (parts[0] ?? "").replace(/</g, " ").replace(/\s+/g, " ").trim();
  const given = (parts.slice(1).join(" ").replace(/</g, " ").replace(/\s+/g, " ").trim()) || cleaned;
  return {
    last_name: surname,
    first_name: given === surname ? "" : given,
  };
}

function sexFromMrz(ch: string): MrzSex {
  if (ch === "M") return "male";
  if (ch === "F") return "female";
  return "other";
}

/** Normalize OCR / paste text into candidate 44-char TD3 lines. */
export function extractTd3Lines(text: string): string[] {
  const normalized = text
    .toUpperCase()
    .replace(/\u003c/g, "<")
    .replace(/[^\nA-Z0-9<]/g, "")
    .replace(/ +/g, "");
  const lines = normalized
    .split(/\n+/)
    .map((line) => line.trim())
    .filter(Boolean)
    .map((line) => (line.length > 44 ? line.slice(0, 44) : line.padEnd(44, "<")));

  const passportLines = lines.filter((line) => line.startsWith("P"));
  if (passportLines.length >= 1) {
    const line1 = passportLines[0];
    const idx = lines.indexOf(line1);
    const line2 = lines[idx + 1];
    if (line2 && /^[A-Z0-9<]{30,}$/.test(line2)) {
      return [line1.padEnd(44, "<").slice(0, 44), line2.padEnd(44, "<").slice(0, 44)];
    }
  }

  // Fallback: any two consecutive 44-ish lines where first starts with P
  for (let i = 0; i < lines.length - 1; i += 1) {
    if (lines[i].startsWith("P") && lines[i + 1].length >= 28) {
      return [lines[i].padEnd(44, "<").slice(0, 44), lines[i + 1].padEnd(44, "<").slice(0, 44)];
    }
  }
  return [];
}

export function parseTd3Mrz(text: string): MrzParseResult {
  const warnings: string[] = [];
  const confidence: MrzFieldConfidence[] = [];
  const rawLines = extractTd3Lines(text);

  if (rawLines.length < 2) {
    return {
      ok: false,
      fields: {},
      confidence: [],
      warnings: ["Could not locate a passport MRZ (two TD3 lines)."],
      checkDigitsValid: false,
      rawLines,
    };
  }

  const [line1, line2] = rawLines;
  if (!line1.startsWith("P")) {
    warnings.push("MRZ line 1 does not start with P (passport).");
  }

  const issuingAlpha3 = line1.slice(2, 5);
  const names = parseNames(line1.slice(5));
  const passportNumber = line2.slice(0, 9).replace(/</g, "");
  const passportCd = line2[9] ?? "";
  const nationalityAlpha3 = line2.slice(10, 13);
  const dobRaw = line2.slice(13, 19);
  const dobCd = line2[19] ?? "";
  const sex = line2[20] ?? "<";
  const expiryRaw = line2.slice(21, 27);
  const expiryCd = line2[27] ?? "";
  const optional = line2.slice(28, 42);
  const optionalCd = line2[42] ?? "";
  const compositeCd = line2[43] ?? "";

  const passportOk = verifyCheckDigit(line2.slice(0, 9), passportCd);
  const dobOk = verifyCheckDigit(dobRaw, dobCd);
  const expiryOk = verifyCheckDigit(expiryRaw, expiryCd);
  const optionalData = optional;
  const optionalOk = optionalData.replace(/</g, "") === "" || verifyCheckDigit(optionalData, optionalCd);
  const compositePayload = `${line2.slice(0, 10)}${dobRaw}${dobCd}${expiryRaw}${expiryCd}${optional}${optionalCd}`;
  const compositeOk = verifyCheckDigit(compositePayload, compositeCd);
  const checkDigitsValid = passportOk && dobOk && expiryOk && optionalOk && compositeOk;

  if (!checkDigitsValid) {
    warnings.push("One or more MRZ check digits are invalid. Verify details against the passport.");
  }

  const dobIso = mrzDateToIso(dobRaw, "dob");
  const expiryIso = mrzDateToIso(expiryRaw, "expiry");
  if (!dobIso) warnings.push("Date of birth could not be parsed from MRZ.");
  if (!expiryIso) warnings.push("Expiry date could not be parsed from MRZ.");

  const fields: Partial<MrzExtractedFields> = {
    last_name: names.last_name,
    first_name: names.first_name,
    passport_number: passportNumber,
    nationality: alpha3ToAlpha2(nationalityAlpha3),
    gender: sexFromMrz(sex),
    passport_issuing_country: alpha3ToAlpha2(issuingAlpha3),
  };
  if (dobIso) fields.date_of_birth = dobIso;
  if (expiryIso) fields.passport_expiry_date = expiryIso;
  // passport_issue_date is never derived from expiry.

  const pushConf = (
    field: keyof MrzExtractedFields,
    level: "high" | "medium" | "low",
    warning?: string,
  ) => {
    confidence.push({ field, confidence: level, warning });
  };

  pushConf("last_name", names.last_name ? "high" : "low", names.last_name ? undefined : "Surname missing");
  pushConf("first_name", names.first_name ? "high" : "medium", names.first_name ? undefined : "Given names incomplete");
  pushConf("passport_number", passportOk ? "high" : "low", passportOk ? undefined : "Passport number check digit failed");
  pushConf("nationality", nationalityAlpha3.includes("<") ? "low" : "high");
  pushConf("date_of_birth", dobOk && dobIso ? "high" : "low", dobOk ? undefined : "DOB check digit failed");
  pushConf("gender", sex === "M" || sex === "F" ? "high" : "medium");
  pushConf("passport_expiry_date", expiryOk && expiryIso ? "high" : "low", expiryOk ? undefined : "Expiry check digit failed");
  pushConf("passport_issuing_country", issuingAlpha3.includes("<") ? "low" : "high");

  const filled = Object.values(fields).filter((v) => typeof v === "string" && v.length > 0).length;
  const ok = filled >= 4 && Boolean(fields.passport_number);

  if (!ok) warnings.push("Partial MRZ extraction — confirm all fields before continuing.");

  return {
    ok,
    fields,
    confidence,
    warnings,
    checkDigitsValid,
    rawLines,
  };
}
