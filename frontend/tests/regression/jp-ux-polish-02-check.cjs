const fs = require("fs");
const path = require("path");
const root = path.join(__dirname, "../..");
const read = (r) => fs.readFileSync(path.join(root, r), "utf8");

const checks = [
  ["features/flight-results/components/PairReturnCard.tsx", (s) => s.includes("paired-strip-departure-badge") && !/>\s*Outbound\s*</.test(s) && !/>\s*Return\s*</.test(s)],
  ["features/standard-booking/components/PassengerDetailsPage.tsx", (s) => s.includes("showInitialSkeleton") && s.includes("loadContext({ soft: true })")],
  ["app/(checkout)/layout.tsx", (s) => !s.includes("hideFooter")],
  ["components/ui/AirlineLogoMark.tsx", (s) => s.includes('data-logo-radius="0"')],
  ["features/public-visual/hero/PublicHero.tsx", (s) => !s.includes("AnimatedFlightPath") && s.includes("homepage-hero-overlap-spacer")],
  ["features/group-ticketing/components/GroupResultCard.tsx", (s) => s.includes("AirlineLogoMark")],
];

let fail = 0;
for (const [f, fn] of checks) {
  const ok = fn(read(f));
  console.log(ok ? "PASS" : "FAIL", f);
  if (!ok) fail += 1;
}
process.exit(fail ? 1 : 0);
