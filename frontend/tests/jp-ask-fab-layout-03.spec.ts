import { test, expect } from "@playwright/test";
import {
  computeAskFabBottomPx,
  computeDockFabBottomPx,
  FAB_DOCK_BOTTOM_PX,
  FAB_DOCK_TRIGGER_PX,
  FAB_GAP_PX,
  FAB_STICKY_CTA_LIFT_PX,
} from "../features/public-floating/public-floating-layout";

const ZERO_METRICS = { visualBottomInset: 0, safeAreaBottom: 0 };
const SAFE_METRICS = { visualBottomInset: 0, safeAreaBottom: 34 };

test.describe("JP-FAB-CLOSURE-01 layout contract", () => {
  test("offsets Ask FAB above dock when AI enabled on mobile", () => {
    const dockBottom = computeDockFabBottomPx(
      {
        aiEnabled: true,
        askOpen: false,
        dockOpen: false,
        liftCheckout: false,
        liftFlightCta: false,
      },
      ZERO_METRICS,
    );
    const askBottom = computeAskFabBottomPx(
      {
        aiEnabled: true,
        askOpen: false,
        dockOpen: false,
        liftCheckout: false,
        liftFlightCta: false,
      },
      ZERO_METRICS,
    );
    expect(askBottom).toBeGreaterThanOrEqual(dockBottom + FAB_DOCK_TRIGGER_PX + FAB_GAP_PX);
  });

  test("lift checkout keeps Ask above dock (no overlap at same bottom)", () => {
    const state = {
      aiEnabled: true,
      askOpen: false,
      dockOpen: false,
      liftCheckout: true,
      liftFlightCta: false,
    };
    const dockBottom = computeDockFabBottomPx(state, ZERO_METRICS);
    const askBottom = computeAskFabBottomPx(state, ZERO_METRICS);
    expect(dockBottom).toBeGreaterThanOrEqual(FAB_STICKY_CTA_LIFT_PX);
    expect(askBottom).toBeGreaterThan(dockBottom);
    expect(askBottom).toBeGreaterThanOrEqual(dockBottom + FAB_DOCK_TRIGGER_PX + FAB_GAP_PX);
  });

  test("dock open adds extra offset", () => {
    const closed = computeAskFabBottomPx(
      {
        aiEnabled: true,
        askOpen: false,
        dockOpen: false,
        liftCheckout: false,
        liftFlightCta: false,
      },
      ZERO_METRICS,
    );
    const open = computeAskFabBottomPx(
      {
        aiEnabled: true,
        askOpen: false,
        dockOpen: true,
        liftCheckout: false,
        liftFlightCta: false,
      },
      ZERO_METRICS,
    );
    expect(open).toBeGreaterThan(closed);
  });

  test("ask open resets dock stack offset", () => {
    const closed = computeAskFabBottomPx(
      {
        aiEnabled: true,
        askOpen: false,
        dockOpen: false,
        liftCheckout: false,
        liftFlightCta: false,
      },
      ZERO_METRICS,
    );
    const askOpen = computeAskFabBottomPx(
      {
        aiEnabled: true,
        askOpen: true,
        dockOpen: false,
        liftCheckout: false,
        liftFlightCta: false,
      },
      ZERO_METRICS,
    );
    expect(askOpen).toBeLessThan(closed);
  });

  test("safe area included in dock bottom", () => {
    const without = computeDockFabBottomPx(
      {
        aiEnabled: false,
        askOpen: false,
        dockOpen: false,
        liftCheckout: false,
        liftFlightCta: false,
      },
      ZERO_METRICS,
    );
    const withSafe = computeDockFabBottomPx(
      {
        aiEnabled: false,
        askOpen: false,
        dockOpen: false,
        liftCheckout: false,
        liftFlightCta: false,
      },
      SAFE_METRICS,
    );
    expect(withSafe - without).toBe(34);
  });

  test("visual viewport inset lifts both controls", () => {
    const metrics = { visualBottomInset: 48, safeAreaBottom: 0 };
    const dock = computeDockFabBottomPx(
      {
        aiEnabled: false,
        askOpen: false,
        dockOpen: false,
        liftCheckout: false,
        liftFlightCta: false,
      },
      metrics,
    );
    expect(dock).toBe(FAB_DOCK_BOTTOM_PX + 48);
  });
});
