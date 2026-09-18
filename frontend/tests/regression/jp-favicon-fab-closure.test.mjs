/**
 * Static regression: JetPakistan favicon authority + FAB dock safe-area contract.
 * Run: node tests/regression/jp-favicon-fab-closure.test.mjs
 */
import { existsSync, readFileSync, statSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

const root = join(dirname(fileURLToPath(import.meta.url)), "../..");
const repoRoot = join(root, "..");
const read = (rel) => readFileSync(join(root, rel), "utf8");

let fail = 0;

function check(label, ok) {
  console.log(ok ? "PASS" : "FAIL", label);
  if (!ok) fail += 1;
}

const faviconPaths = [
  join(root, "app/favicon.ico"),
  join(root, "public/favicon.ico"),
  join(repoRoot, "public/favicon.ico"),
  join(repoRoot, "public/client-assets/jetpk/favicon/favicon.ico"),
];

for (const path of faviconPaths) {
  const size = existsSync(path) ? statSync(path).size : 0;
  check(`favicon exists and size>0: ${path.replace(/\\/g, "/")}`, size > 0);
}

const layout = read("app/layout.tsx");
check(
  "root layout generateMetadata resolves favicon via PublicConfig",
  /generateMetadata/.test(layout) && /resolveFaviconUrl/.test(layout) && /favicon_url/.test(layout),
);
check("root layout does not hard-code only static metadata.icons object", !/export const metadata:\s*Metadata/.test(layout));

const publicLayout = read("app/(public)/layout.tsx");
check("public layout generateMetadata sets icons from resolveFaviconUrl", /resolveFaviconUrl/.test(publicLayout));

const resolveFavicon = read("lib/branding/resolve-favicon.ts");
check("resolve-favicon falls back to /favicon.ico", resolveFavicon.includes('CANONICAL_JETPK_FAVICON_PATH = "/favicon.ico"'));

const resolveLogo = read("lib/branding/resolve-header-logo.ts");
check("header logo fallback uses existing logo.svg", resolveLogo.includes("/client-assets/jetpk/logo/logo.svg"));

const globals = read("app/globals.css");
check("globals.css defines .jp-public-fab-dock--base safe-area bottom", globals.includes(".jp-public-fab-dock--base"));
check(
  "globals.css base bottom uses safe-area-inset-bottom",
  /jp-public-fab-dock--base[\s\S]*?safe-area-inset-bottom/.test(globals),
);
check("globals.css defines .jp-public-fab-dock--lift", globals.includes(".jp-public-fab-dock--lift"));
check("globals.css defines .jp-fab-content-clear", globals.includes(".jp-fab-content-clear"));
check(
  "jp-fab-content-clear clears Ask FAB footprint (>=5.5rem)",
  /jp-fab-content-clear[\s\S]*?5\.5rem/.test(globals),
);
check("frontend/app/icon.png removed (no default Next metadata icon)", !existsSync(join(root, "app/icon.png")));
check(
  "globals.css hides dock while Ask panel open",
  /data-jp-ask-open="1"[\s\S]*?\.jp-public-fab-dock/.test(globals),
);

const resultsPage = read("features/flight-results/components/FlightResultsPage.tsx");
check("FlightResultsPage uses jp-fab-content-clear", resultsPage.includes("jp-fab-content-clear"));

const returnForm = read("features/search/components/ReturnForm.tsx");
check(
  "ReturnForm Search CTA uses calc width to clear FAB on mobile",
  /max-lg:w-\[calc\(100%-4\.5rem\)\]/.test(returnForm),
);

const dock = read("components/navigation/PublicFloatingActionDock.tsx");
check("dock uses class jp-public-fab-dock", dock.includes("jp-public-fab-dock"));
check("dock uses safe-area-inset-right", dock.includes("safe-area-inset-right"));

const sticky = read("features/booking-layout/components/MobileOrderSummary.tsx");
check(
  "MobileStickyAction reserves right padding for FAB",
  sticky.includes("safe-area-inset-right") && sticky.includes("3.75rem"),
);
check(
  "MobileStickyAction uses safe-area-inset-bottom",
  sticky.includes("safe-area-inset-bottom"),
);

process.exit(fail ? 1 : 0);
