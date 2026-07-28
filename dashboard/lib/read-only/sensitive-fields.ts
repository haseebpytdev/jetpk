/** Keys that must never appear in dashboard read-only API responses or UI payloads. */
export const SENSITIVE_FIELD_KEYS = [
  "password",
  "passwordHash",
  "password_hash",
  "mfaSecret",
  "mfa_secret",
  "recoveryCodes",
  "recovery_codes",
  "sessionId",
  "session_id",
  "csrfToken",
  "csrf_token",
  "apiKey",
  "api_key",
  "supplierCredential",
  "supplier_credentials",
  "pcc",
  "lniata",
  "cardNumber",
  "card_number",
  "cvv",
  "pan",
  "passportNumber",
  "passport_number",
  "nationalId",
  "national_id",
] as const;

export type SensitiveFieldKey = (typeof SENSITIVE_FIELD_KEYS)[number];

export function stripSensitiveFields<T extends Record<string, unknown>>(record: T): T {
  const next = { ...record };
  for (const key of Object.keys(next)) {
    if (SENSITIVE_FIELD_KEYS.includes(key as SensitiveFieldKey)) {
      delete next[key];
    }
  }
  return next;
}

export function containsSensitiveKeys(value: unknown, depth = 0): boolean {
  if (depth > 6 || value === null || value === undefined) {
    return false;
  }
  if (Array.isArray(value)) {
    return value.some((item) => containsSensitiveKeys(item, depth + 1));
  }
  if (typeof value === "object") {
    for (const [key, nested] of Object.entries(value as Record<string, unknown>)) {
      if (SENSITIVE_FIELD_KEYS.includes(key as SensitiveFieldKey)) {
        return true;
      }
      if (containsSensitiveKeys(nested, depth + 1)) {
        return true;
      }
    }
  }
  return false;
}
