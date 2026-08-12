/**
 * Durable regression: agency field formatting must never emit [object Object].
 */
const { createRequire } = require("module");
const requireFrom = createRequire(__filename);
const { formatAgencyFieldValue, pickAgencyDisplayFields } = requireFrom("./agency-display.cjs");

function assert(condition, message) {
  if (!condition) {
    console.error("FAIL:", message);
    process.exit(1);
  }
}

const incomplete = formatAgencyFieldValue({
  is_complete: false,
  missing_fields: ["Phone", "City"],
});
assert(incomplete === "Incomplete (Phone, City)", `unexpected incomplete: ${incomplete}`);
assert(formatAgencyFieldValue({ is_complete: true, missing_fields: [] }) === "Verified", "verified label");
assert(formatAgencyFieldValue({ nested: true }) == null, "opaque objects suppressed");

const fields = pickAgencyDisplayFields({
  agency_name: "JP QA Agency",
  agent_code: "JPQA01",
  verification: { is_complete: true, missing_fields: [] },
  logo_url: "https://example.com/logo.png",
  nested_noise: { foo: "bar" },
});
assert(fields.every((field) => !String(field.value).includes("[object Object]")), "fields clean");
assert(fields.find((f) => f.key === "verification")?.value === "Verified", "verification field");
console.log("agency-display regression PASS");
