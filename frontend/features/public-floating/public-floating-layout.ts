/**
 * Shared layout contract for Ask JetPakistan FAB + PublicFloatingActionDock.
 * Coordinates bottom offsets so controls never overlap on sub-lg viewports.
 */

export const PUBLIC_FLOATING_LAYOUT_EVENT = "jp-public-floating-layout";

export type PublicFloatingLayoutState = {
  aiEnabled: boolean;
  askOpen: boolean;
  dockOpen: boolean;
  liftCheckout: boolean;
  liftFlightCta: boolean;
};

export const FAB_DOCK_SIZE_PX = 56;
export const FAB_GAP_PX = 12;
export const FAB_DOCK_PANEL_OFFSET_PX = 72;

export function computeAskFabBottomPx(state: PublicFloatingLayoutState): number {
  const safe = 18;
  let bottom = safe;

  if (state.liftCheckout || state.liftFlightCta) {
    bottom = Math.max(bottom, 108);
  }

  if (state.aiEnabled && !state.askOpen) {
    bottom += FAB_DOCK_SIZE_PX + FAB_GAP_PX;
    if (state.dockOpen) {
      bottom += FAB_DOCK_PANEL_OFFSET_PX;
    }
  }

  return bottom;
}

export function applyPublicFloatingLayout(state: PublicFloatingLayoutState): void {
  if (typeof document === "undefined") return;

  const root = document.documentElement;
  root.dataset.jpAiEnabled = state.aiEnabled ? "1" : "0";
  root.dataset.jpAskOpen = state.askOpen ? "1" : "0";
  root.dataset.jpDockOpen = state.dockOpen ? "1" : "0";
  root.dataset.jpFabLift = state.liftCheckout || state.liftFlightCta ? "1" : "0";

  const askBottom = computeAskFabBottomPx(state);
  root.style.setProperty("--jp-ask-fab-bottom", `${askBottom}px`);
  root.style.setProperty(
    "--jp-dock-fab-bottom",
    state.liftCheckout || state.liftFlightCta ? "max(6.75rem, calc(env(safe-area-inset-bottom) + 5.5rem))" : "max(1rem, env(safe-area-inset-bottom))",
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
