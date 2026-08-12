function formatAgencyFieldValue(value) {
  if (value == null || value === "") return null;
  if (typeof value === "string" || typeof value === "number" || typeof value === "boolean") {
    return String(value);
  }
  if (Array.isArray(value)) {
    const parts = value.map((item) => formatAgencyFieldValue(item)).filter(Boolean);
    return parts.length ? parts.join(", ") : null;
  }
  if (typeof value === "object") {
    if ("is_complete" in value) {
      if (value.is_complete === true) return "Verified";
      const missing = Array.isArray(value.missing_fields)
        ? value.missing_fields.filter((item) => typeof item === "string")
        : [];
      if (missing.length) return `Incomplete (${missing.join(", ")})`;
      return "Pending";
    }
    if ("status" in value && (typeof value.status === "string" || typeof value.status === "number")) {
      return String(value.status);
    }
    if ("label" in value && typeof value.label === "string") return value.label;
    return null;
  }
  return null;
}

const FIELD_ORDER = [
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

function pickAgencyDisplayFields(agency) {
  const fields = [];
  const seen = new Set();
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

module.exports = { formatAgencyFieldValue, pickAgencyDisplayFields };
