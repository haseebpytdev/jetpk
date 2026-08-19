import assert from "node:assert/strict";
import test from "node:test";

function passengerSummary(passengers, cabinLabel, compact = false) {
  const total = passengers.adults + passengers.children + passengers.infants;
  if (compact && total >= 5) {
    return `${total} Travellers · ${cabinLabel}`;
  }
  const parts = [];
  parts.push(`${passengers.adults} Adult${passengers.adults === 1 ? "" : "s"}`);
  if (passengers.children > 0) {
    parts.push(`${passengers.children} Child${passengers.children === 1 ? "" : "ren"}`);
  }
  if (passengers.infants > 0) {
    parts.push(`${passengers.infants} Infant${passengers.infants === 1 ? "" : "s"}`);
  }
  return `${parts.join(", ")} · ${cabinLabel}`;
}

function validatePassengers(passengers) {
  const errors = [];
  if (passengers.adults < 1) errors.push("At least one adult is required.");
  if (passengers.infants > passengers.adults) errors.push("Infants cannot exceed adults.");
  return errors;
}

test("passenger summary keeps cabin visible in compact mode", () => {
  const summary = passengerSummary({ adults: 5, children: 0, infants: 0 }, "Economy", true);
  assert.equal(summary, "5 Travellers · Economy");
});

test("passenger summary uses singular adult label", () => {
  const summary = passengerSummary({ adults: 1, children: 0, infants: 0 }, "Economy");
  assert.equal(summary, "1 Adult · Economy");
});

test("infant validation remains enforced", () => {
  const errors = validatePassengers({ adults: 1, children: 0, infants: 2 });
  assert.match(errors.join(" "), /Infants cannot exceed adults/i);
});

test("first cabin serializes in query params", () => {
  const params = new URLSearchParams();
  params.set("cabin", "first");
  params.set("return_date", "2026-09-08");
  assert.equal(params.get("cabin"), "first");
  assert.equal(params.get("return_date"), "2026-09-08");
});
