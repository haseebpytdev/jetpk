/**
 * JP-UI-PERF-STABILITY-01C — floating layout unit contract.
 * Run: node --experimental-strip-types tests/regression/jp-ui-perf-01c-floating-layout.test.mjs
 */
import { readFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath, pathToFileURL } from "node:url";

const root = join(dirname(fileURLToPath(import.meta.url)), "../..");
const layoutUrl = pathToFileURL(
  join(root, "features/public-floating/public-floating-layout.ts"),
).href;

const {
  FAB_SAFE_BOTTOM_PX,
  FAB_DOCK_BOTTOM_PX,
  FAB_DOCK_LIFT_BOTTOM_PX,
  FAB_DOCK_TRIGGER_PX,
  FAB_GAP_PX,
  computeAskFabBottomPx,
  computeDockBottomPx,
  computeAskFabBottomCss,
  computeDockBottomCss,
  DOCK_BOTTOM_CSS_BASE,
  DOCK_BOTTOM_CSS_LIFT,
  ASK_BOTTOM_CSS_STANDALONE,
} = await import(layoutUrl);

let fail = 0;
function check(label, ok) {
  console.log(ok ? "PASS" : "FAIL", label);
  if (!ok) fail += 1;
}

const baseClosed = {
  aiEnabled: true,
  askOpen: false,
  dockOpen: false,
  liftCheckout: false,
  liftFlightCta: false,
  viewportIsSubLg: true,
};

const liftedClosed = {
  ...baseClosed,
  liftFlightCta: true,
};

const dockOpen = { ...liftedClosed, dockOpen: true };
const askOpen = { ...liftedClosed, askOpen: true };
const aiOff = { ...liftedClosed, aiEnabled: false };
const desktop = { ...liftedClosed, viewportIsSubLg: false };

const expectedBaseAsk = FAB_DOCK_BOTTOM_PX + FAB_DOCK_TRIGGER_PX + FAB_GAP_PX;
const expectedLiftedAsk =
  FAB_DOCK_LIFT_BOTTOM_PX + FAB_DOCK_TRIGGER_PX + FAB_GAP_PX;

check("baseline regression constant: lifted dock bottom is 108", FAB_DOCK_LIFT_BOTTOM_PX === 108);
check(
  "BASELINE old bug would collide (lifted ask == lifted dock) — documented",
  FAB_DOCK_LIFT_BOTTOM_PX === 108 && expectedLiftedAsk > FAB_DOCK_LIFT_BOTTOM_PX,
);

check("base dock closed → Ask stacks at 86", computeAskFabBottomPx(baseClosed) === expectedBaseAsk);
check(
  "lifted dock closed → Ask clears dock (178, not 108)",
  computeAskFabBottomPx(liftedClosed) === expectedLiftedAsk,
);
check(
  "ASK_BOTTOM >= DOCK_TOP + gap (lifted)",
  computeAskFabBottomPx(liftedClosed) >=
    computeDockBottomPx(liftedClosed) + FAB_DOCK_TRIGGER_PX + FAB_GAP_PX,
);
check("dock open → Ask uses standalone safe bottom", computeAskFabBottomPx(dockOpen) === FAB_SAFE_BOTTOM_PX);
check("Ask open → Ask uses standalone safe bottom", computeAskFabBottomPx(askOpen) === FAB_SAFE_BOTTOM_PX);
check("AI disabled → Ask uses standalone safe bottom", computeAskFabBottomPx(aiOff) === FAB_SAFE_BOTTOM_PX);
check(
  "desktop dock hidden → Ask does not reserve dock stack",
  computeAskFabBottomPx(desktop) === FAB_SAFE_BOTTOM_PX,
);

check("dock CSS base preserves safe-area", computeDockBottomCss(baseClosed) === DOCK_BOTTOM_CSS_BASE);
check("dock CSS lift preserves safe-area", computeDockBottomCss(liftedClosed) === DOCK_BOTTOM_CSS_LIFT);
check(
  "Ask CSS stacks from --jp-dock-fab-bottom on sub-lg",
  computeAskFabBottomCss(liftedClosed).includes("var(--jp-dock-fab-bottom)"),
);
check(
  "Ask CSS desktop/standalone preserves safe-area",
  computeAskFabBottomCss(desktop) === ASK_BOTTOM_CSS_STANDALONE &&
    ASK_BOTTOM_CSS_STANDALONE.includes("safe-area-inset-bottom"),
);

const source = readFileSync(
  join(root, "features/public-floating/public-floating-layout.ts"),
  "utf8",
);
check("FAB_DOCK_PANEL_OFFSET_PX removed from layout authority", !source.includes("FAB_DOCK_PANEL_OFFSET_PX"));

const globals = readFileSync(join(root, "app/globals.css"), "utf8");
check(
  "globals mutual exclusivity: dock open hides Ask wrap",
  /data-jp-dock-open="1"[\s\S]*?data-jp-ask-fab-wrap/.test(globals),
);
check(
  "globals mutual exclusivity: ask open hides dock",
  /data-jp-ask-open="1"[\s\S]*?\.jp-public-fab-dock/.test(globals),
);
check("safe-area-inset-right still present in dock component or globals", true);
const dock = readFileSync(
  join(root, "components/navigation/PublicFloatingActionDock.tsx"),
  "utf8",
);
check("dock preserves safe-area-inset-right", dock.includes("safe-area-inset-right"));
check("Ask CSS preserves safe-area-inset-right", readFileSync(
  join(root, "features/ai-assistant/components/AskJetPakistanChat.module.css"),
  "utf8",
).includes("safe-area-inset-right"));

process.exit(fail ? 1 : 0);
