/**
 * Static regression: PairReturnCard exposes dual legs; FlightResultsPage pair branch uses it.
 * Run: node tests/regression/jp-pair-return-card-dual-legs.test.mjs
 */
import { readFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

const root = join(dirname(fileURLToPath(import.meta.url)), "../..");
const read = (rel) => readFileSync(join(root, rel), "utf8");

let fail = 0;

function check(label, ok) {
  console.log(ok ? "PASS" : "FAIL", label);
  if (!ok) fail += 1;
}

const pairCard = read("features/flight-results/components/PairReturnCard.tsx");
check('PairReturnCard has data-testid="pair-return-card"', pairCard.includes('data-testid="pair-return-card"'));
check('PairReturnCard has data-leg="outbound"', pairCard.includes('data-leg="outbound"'));
check('PairReturnCard has data-leg="return"', pairCard.includes('data-leg="return"'));

const page = read("features/flight-results/components/FlightResultsPage.tsx");
check("FlightResultsPage imports PairReturnCard", page.includes("PairReturnCard"));
check("FlightResultsPage pair branch renders PairReturnCard", /isReturnPair[\s\S]*?<PairReturnCard/.test(page));
check(
  "FlightResultsPage does not render OutboundOptionCard on pair branch before split",
  /isReturnPair[\s\S]*?<PairReturnCard[\s\S]*?: results\.isReturnSplit[\s\S]*?<OutboundOptionCard/.test(page),
);

process.exit(fail ? 1 : 0);
