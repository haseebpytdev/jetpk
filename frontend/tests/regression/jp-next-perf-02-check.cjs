const fs = require("fs");
const path = require("path");
const root = path.join(__dirname, "../..");
const read = (r) => fs.readFileSync(path.join(root, r), "utf8");

const checks = [
  [
    "features/group-ticketing/components/GroupsLandingPage.tsx",
    (s) => !s.includes("720") && !s.includes("setTimeout(() => setSearchPhase"),
  ],
  [
    "app/(public)/groups/search/page.tsx",
    (s) =>
      s.includes("fetchGroupSearchDataServer") &&
      s.includes("Promise.all") &&
      s.includes("initialResults"),
  ],
  [
    "features/group-ticketing/components/GroupSearchPage.tsx",
    (s) =>
      s.includes("initialResults") &&
      s.includes("seedGroupSearchFacetsCache") &&
      s.includes("hydratedSsrKey") &&
      s.includes("disabled={false}"),
  ],
  [
    "features/group-ticketing/services/group-ticketing-api.ts",
    (s) => s.includes("fetchGroupSearchDataServer") && s.includes("fetchGroupSearchFacetsServer"),
  ],
  [
    "features/flight-results/hooks/use-flight-results.ts",
    (s) =>
      s.includes("Updating results…") &&
      s.includes("viewChanged") &&
      s.includes("viewPayloadCacheRef") &&
      s.includes("Switching view…") &&
      !s.includes("View / filter / sort changes must not leave the prior flow's cards on screen."),
  ],
  [
    "features/flight-results/components/FlightResultsPage.tsx",
    (s) =>
      s.includes("viewOverride") &&
      s.includes("INITIAL_VISIBLE_CARDS") &&
      s.includes("history.replaceState"),
  ],
  [
    "features/standard-booking/services/commerce-gates-service.ts",
    (s) =>
      s.includes("guest_booking_enabled") &&
      s.includes("DEFAULT_GATES") &&
      s.includes("never serialize session bootstrap"),
  ],
  [
    "features/flight-results/services/flight-results-api.ts",
    (s) => s.includes("RevalidateOfferTiming") && s.includes("supplier_ms"),
  ],
  [
    "features/standard-booking/components/BookingReviewPage.tsx",
    (s) => s.includes("review-loading-shell") && s.includes("soft"),
  ],
  [
    "features/standard-booking/components/ManualPaymentPage.tsx",
    (s) => s.includes("payment-loading-shell"),
  ],
  [
    "features/standard-booking/components/PassengerDetailsPage.tsx",
    (s) => s.includes('router.prefetch("/booking/review")'),
  ],
];

let fail = 0;
for (const [f, fn] of checks) {
  const ok = fn(read(f));
  console.log(ok ? "PASS" : "FAIL", f);
  if (!ok) fail += 1;
}
process.exit(fail ? 1 : 0);
