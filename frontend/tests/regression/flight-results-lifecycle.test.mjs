import assert from "node:assert/strict";

function searchIdentityKey(params) {
  const searchId = params.get("search_id")?.trim();
  if (searchId) return `id:${searchId}`;
  const keys = ["trip_type", "from", "to", "depart", "return_date", "adults", "children", "infants", "cabin", "include_nearby", "flexible_dates"];
  const parts = keys.map((key) => `${key}=${params.get(key) ?? ""}`);
  parts.push(`multi_from=${params.getAll("multi_from[]").join(",")}`);
  parts.push(`multi_to=${params.getAll("multi_to[]").join(",")}`);
  parts.push(`multi_depart=${params.getAll("multi_depart[]").join(",")}`);
  if (params.get("stops") === "direct") parts.push("direct=1");
  return parts.join("&");
}

function resolvePassengerCheckoutHandoffUrl(pathOrUrl) {
  const normalized = pathOrUrl.startsWith("/") ? pathOrUrl : `/${pathOrUrl}`;
  if (normalized.startsWith("/booking/passengers") || /(?:^|\/)booking\/passengers(?:\?|$)/.test(normalized)) {
    const match = normalized.match(/(\/booking\/passengers(?:\?.*)?)$/);
    return match ? match[1] : "/booking/passengers";
  }
  return normalized;
}

const base = new URLSearchParams({
  search_id: "abc",
  trip_type: "one_way",
  from: "ISB",
  to: "DXB",
  depart: "2026-09-01",
  adults: "1",
  sort: "recommended",
});
const withAirline = new URLSearchParams(base);
withAirline.set("airline", "EK");
assert.equal(searchIdentityKey(base), searchIdentityKey(withAirline));

const noId = new URLSearchParams({
  trip_type: "one_way",
  from: "ISB",
  to: "DXB",
  depart: "2026-09-01",
  adults: "1",
});
const noIdSort = new URLSearchParams(noId);
noIdSort.set("sort", "lowest_price");
assert.equal(searchIdentityKey(noId), searchIdentityKey(noIdSort));

assert.equal(resolvePassengerCheckoutHandoffUrl("/booking/passengers?search_id=1"), "/booking/passengers?search_id=1");
assert.equal(
  resolvePassengerCheckoutHandoffUrl("/jetpk/booking/passengers?search_id=1"),
  "/booking/passengers?search_id=1",
);

console.log("flight-results-lifecycle.test.mjs PASS");
