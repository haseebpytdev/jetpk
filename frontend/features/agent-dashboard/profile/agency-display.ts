export type AgencyDisplayField = {
  key: string;
  label: string;
  value: string;
};

const FIELD_ORDER: Array<{ key: string; label: string }> = [
  { key: "agency_name", label: "Agency name" },
  { key: "legal_name", label: "Legal name" },
  { key: "agent_code", label: "Agent code" },
  { key: "email", label: "Email" },
  { key: "phone", label: "Phone" },
  { key: "whatsapp", label: "WhatsApp" },
  { key: "city", label: "City" },
  { key: "country", label: "Country" },
  { key: "license_number", label: "License number" },
  { key: "tax_number", label: "Tax number" },
  { key: "address", label: "Address" },
  { key: "verification", label: "Verification" },
];

const SKIP_KEYS = new Set([
  "logo_url",
  "logo_path",
  "id",
  "user_id",
  "agency_id",
  "platform_agency_name",
  "registration_number",
  "meta",
]);

export function formatAgencyFieldValue(value: unknown): string | null {
  if (value == null || value === "") return null;
  if (typeof value === "string" || typeof value === "number" || typeof value === "boolean") {
    return String(value);
  }
  if (Array.isArray(value)) {
    const parts = value.map((item) => formatAgencyFieldValue(item)).filter(Boolean) as string[];
    return parts.length ? parts.join(", ") : null;
  }
  if (typeof value === "object") {
    const record = value as Record<string, unknown>;
    if ("is_complete" in record) {
      if (record.is_complete === true) return "Verified";
      const missing = Array.isArray(record.missing_fields)
        ? record.missing_fields.filter((item): item is string => typeof item === "string")
        : [];
      if (missing.length) return `Incomplete (${missing.join(", ")})`;
      return "Pending";
    }
    if ("status" in record && (typeof record.status === "string" || typeof record.status === "number")) {
      return String(record.status);
    }
    if ("label" in record && typeof record.label === "string") return record.label;
    // Never stringify raw objects into the UI.
    return null;
  }
  return null;
}

export function pickAgencyDisplayFields(agency: Record<string, unknown>): AgencyDisplayField[] {
  const fields: AgencyDisplayField[] = [];
  const seen = new Set<string>();

  for (const item of FIELD_ORDER) {
    if (!(item.key in agency) || SKIP_KEYS.has(item.key)) continue;
    const formatted = formatAgencyFieldValue(agency[item.key]);
    if (!formatted) continue;
    fields.push({ key: item.key, label: item.label, value: formatted });
    seen.add(item.key);
  }

  for (const [key, value] of Object.entries(agency)) {
    if (seen.has(key) || SKIP_KEYS.has(key)) continue;
    if (key.endsWith("_id") || key.includes("secret") || key.includes("token")) continue;
    const formatted = formatAgencyFieldValue(value);
    if (!formatted) continue;
    fields.push({
      key,
      label: key.replace(/_/g, " ").replace(/\b\w/g, (c) => c.toUpperCase()),
      value: formatted,
    });
  }

  return fields;
}
