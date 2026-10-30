/**
 * Shared layout contract for Ask JetPakistan FAB + PublicFloatingActionDock.
 * Dock bottom is the authority on sub-lg; Ask stacks above it with a fixed gap.
 */

export const PUBLIC_FLOATING_LAYOUT_EVENT = "jp-public-floating-layout";

export type PublicFloatingLayoutState = {
  aiEnabled: boolean;
  askOpen: boolean;
  dockOpen: boolean;
  liftCheckout: boolean;
  liftFlightCta: boolean;
  /** When false (lg+), dock is hidden and Ask uses standalone bottom. */
  viewportIsSubLg: boolean;
};

export const FAB_SAFE_BOTTOM_PX = 18;
export const FAB_DOCK_BOTTOM_PX = 16;
/** Matches CSS max(6.75rem, …) lift used above sticky booking/flight CTAs. */
export const FAB_DOCK_LIFT_BOTTOM_PX = 108;
export const FAB_DOCK_TRIGGER_PX = 56;
export const FAB_ASK_SIZE_PX = 58;
export const FAB_GAP_PX = 14;

export const DOCK_BOTTOM_CSS_BASE =
  "max(1rem, env(safe-area-inset-bottom))";
export const DOCK_BOTTOM_CSS_LIFT =
  "max(6.75rem, calc(env(safe-area-inset-bottom) + 5.5rem))";
export const ASK_BOTTOM_CSS_STANDALONE =
  "max(18px, env(safe-area-inset-bottom))";

export function computeDockBottomPx(state: PublicFloatingLayoutState): number {
  if (state.liftCheckout || state.liftFlightCta) {
    return FAB_DOCK_LIFT_BOTTOM_PX;
  }
  return FAB_DOCK_BOTTOM_PX;
}

export function computeDockBottomCss(state: PublicFloatingLayoutState): string {
  if (state.liftCheckout || state.liftFlightCta) {
    return DOCK_BOTTOM_CSS_LIFT;
  }
  return DOCK_BOTTOM_CSS_BASE;
}

/**
 * Pixel bottom for Ask FAB geometry assertions / JS consumers.
 * Sub-lg + AI: stacks above the (possibly lifted) dock trigger.
 * Desktop / dock-open / ask-open / AI-off: standalone safe bottom (FAB may be hidden).
 */
export function computeAskFabBottomPx(state: PublicFloatingLayoutState): number {
  if (
    !state.aiEnabled ||
    state.askOpen ||
    state.dockOpen ||
    !state.viewportIsSubLg
  ) {
    return FAB_SAFE_BOTTOM_PX;
  }

  return computeDockBottomPx(state) + FAB_DOCK_TRIGGER_PX + FAB_GAP_PX;
}

export function computeAskFabBottomCss(state: PublicFloatingLayoutState): string {
  if (
    !state.aiEnabled ||
    state.askOpen ||
    state.dockOpen ||
    !state.viewportIsSubLg
  ) {
    return ASK_BOTTOM_CSS_STANDALONE;
  }

  // Derive from the dock bottom expression so safe-area stays in the chain.
  return `calc(var(--jp-dock-fab-bottom) + ${FAB_DOCK_TRIGGER_PX}px + ${FAB_GAP_PX}px)`;
}

export function applyPublicFloatingLayout(state: PublicFloatingLayoutState): void {
  if (typeof document === "undefined") return;

  const root = document.documentElement;
  root.dataset.jpAiEnabled = state.aiEnabled ? "1" : "0";
  root.dataset.jpAskOpen = state.askOpen ? "1" : "0";
  root.dataset.jpDockOpen = state.dockOpen ? "1" : "0";
  root.dataset.jpFabLift = state.liftCheckout || state.liftFlightCta ? "1" : "0";
  root.dataset.jpFabSubLg = state.viewportIsSubLg ? "1" : "0";

  root.style.setProperty("--jp-dock-fab-bottom", computeDockBottomCss(state));
  root.style.setProperty("--jp-ask-fab-bottom", computeAskFabBottomCss(state));
}

export function publishPublicFloatingLayout(state: PublicFloatingLayoutState): void {
  applyPublicFloatingLayout(state);
  if (typeof window !== "undefined") {
    window.dispatchEvent(
      new CustomEvent(PUBLIC_FLOATING_LAYOUT_EVENT, { detail: state }),
    );
  }
}
