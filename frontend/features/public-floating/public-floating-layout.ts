/**
 * Shared layout contract for Ask JetPakistan FAB + PublicFloatingActionDock.
 * Single geometry authority: safe-area + visual viewport + stack offsets.
 */

export const PUBLIC_FLOATING_LAYOUT_EVENT = "jp-public-floating-layout";

export type PublicFloatingLayoutState = {
  aiEnabled: boolean;
  askOpen: boolean;
  dockOpen: boolean;
  liftCheckout: boolean;
  liftFlightCta: boolean;
};

/** Measured viewport metrics for fixed-position FAB anchoring. */
export type VisualViewportMetrics = {
  /** CSS px from layout viewport bottom to visual viewport bottom (browser chrome). */
  visualBottomInset: number;
  /** Parsed safe-area-inset-bottom in CSS px (0 when unavailable). */
  safeAreaBottom: number;
};

export const FAB_SAFE_BOTTOM_PX = 18;
export const FAB_DOCK_BOTTOM_PX = 16;
export const FAB_DOCK_TRIGGER_PX = 56;
export const FAB_ASK_SIZE_PX = 58;
export const FAB_GAP_PX = 12;
export const FAB_DOCK_PANEL_OFFSET_PX = 72;
export const FAB_STICKY_CTA_LIFT_PX = 108;
export const FAB_MIN_TOUCH_TARGET_PX = 48;

/** Z-index contract for public floating controls (documented stack). */
export const Z_INDEX_PUBLIC_FAB_DOCK = 50;
export const Z_INDEX_ASK_FAB = 60;
export const Z_INDEX_ASK_PANEL = 70;

const DEFAULT_METRICS: VisualViewportMetrics = {
  visualBottomInset: 0,
  safeAreaBottom: 0,
};

export function readSafeAreaBottomPx(): number {
  if (typeof document === "undefined") return 0;
  const probe = document.createElement("div");
  probe.style.cssText =
    "position:fixed;bottom:0;left:0;padding-bottom:env(safe-area-inset-bottom);visibility:hidden;pointer-events:none";
  document.documentElement.appendChild(probe);
  const inset = probe.offsetHeight;
  probe.remove();
  return inset;
}

export function readVisualViewportMetrics(): VisualViewportMetrics {
  if (typeof window === "undefined") return DEFAULT_METRICS;

  const safeAreaBottom = readSafeAreaBottomPx();
  const vv = window.visualViewport;

  if (!vv) {
    return { visualBottomInset: 0, safeAreaBottom };
  }

  const visualBottomInset = Math.max(
    0,
    window.innerHeight - vv.height - vv.offsetTop,
  );

  return { visualBottomInset, safeAreaBottom };
}

export function computeDockFabBottomPx(
  state: PublicFloatingLayoutState,
  metrics: VisualViewportMetrics = DEFAULT_METRICS,
): number {
  let stack = FAB_DOCK_BOTTOM_PX;

  if (state.liftCheckout || state.liftFlightCta) {
    stack = Math.max(stack, FAB_STICKY_CTA_LIFT_PX);
  }

  return (
    metrics.safeAreaBottom + metrics.visualBottomInset + stack
  );
}

export function computeDockPanelMaxHeightPx(
  state: PublicFloatingLayoutState,
  metrics: VisualViewportMetrics = DEFAULT_METRICS,
): number {
  if (typeof window === "undefined") return 352;

  const vv = window.visualViewport;
  const viewportHeight = vv
    ? vv.height + vv.offsetTop
    : window.innerHeight;
  const dockBottom = computeDockFabBottomPx(state, metrics);
  const reservedBottom = dockBottom + FAB_DOCK_TRIGGER_PX + FAB_GAP_PX + 12;

  return Math.max(120, Math.floor(viewportHeight - reservedBottom));
}

export function computeAskFabBottomPx(
  state: PublicFloatingLayoutState,
  metrics: VisualViewportMetrics = DEFAULT_METRICS,
): number {
  if (state.askOpen) {
    return metrics.safeAreaBottom + metrics.visualBottomInset + FAB_SAFE_BOTTOM_PX;
  }

  let bottom = metrics.safeAreaBottom + metrics.visualBottomInset + FAB_SAFE_BOTTOM_PX;

  if (state.aiEnabled) {
    const dockBottom = computeDockFabBottomPx(state, metrics);
    bottom = Math.max(
      bottom,
      dockBottom + FAB_DOCK_TRIGGER_PX + FAB_GAP_PX,
    );
    if (state.dockOpen) {
      bottom += FAB_DOCK_PANEL_OFFSET_PX;
    }
  } else if (state.liftCheckout || state.liftFlightCta) {
    bottom = Math.max(bottom, computeDockFabBottomPx(state, metrics));
  }

  return bottom;
}

export function applyPublicFloatingLayout(
  state: PublicFloatingLayoutState,
  metrics?: VisualViewportMetrics,
): void {
  if (typeof document === "undefined") return;

  const resolvedMetrics = metrics ?? readVisualViewportMetrics();
  const root = document.documentElement;

  root.dataset.jpAiEnabled = state.aiEnabled ? "1" : "0";
  root.dataset.jpAskOpen = state.askOpen ? "1" : "0";
  root.dataset.jpDockOpen = state.dockOpen ? "1" : "0";
  root.dataset.jpFabLift =
    state.liftCheckout || state.liftFlightCta ? "1" : "0";

  const dockBottom = computeDockFabBottomPx(state, resolvedMetrics);
  const askBottom = computeAskFabBottomPx(state, resolvedMetrics);

  root.style.setProperty("--jp-dock-fab-bottom", `${dockBottom}px`);
  root.style.setProperty("--jp-ask-fab-bottom", `${askBottom}px`);
  root.style.setProperty(
    "--jp-fab-visual-bottom-inset",
    `${resolvedMetrics.visualBottomInset}px`,
  );
  root.style.setProperty(
    "--jp-dock-panel-max-height",
    `${computeDockPanelMaxHeightPx(state, resolvedMetrics)}px`,
  );
}

export function publishPublicFloatingLayout(state: PublicFloatingLayoutState): void {
  applyPublicFloatingLayout(state);
  if (typeof window !== "undefined") {
    window.dispatchEvent(
      new CustomEvent(PUBLIC_FLOATING_LAYOUT_EVENT, { detail: state }),
    );
  }
}

/** True when two axis-aligned boxes overlap by more than `tolerance` px. */
export function rectsOverlap(
  a: { x: number; y: number; width: number; height: number },
  b: { x: number; y: number; width: number; height: number },
  tolerance = 0,
): boolean {
  return !(
    a.x + a.width <= b.x + tolerance ||
    b.x + b.width <= a.x + tolerance ||
    a.y + a.height <= b.y + tolerance ||
    b.y + b.height <= a.y + tolerance
  );
}
