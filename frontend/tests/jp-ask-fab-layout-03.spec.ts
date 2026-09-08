import { test, expect } from "@playwright/test";
import {
  computeAskFabBottomPx,
  FAB_DOCK_SIZE_PX,
  FAB_GAP_PX,
} from "../features/public-floating/public-floating-layout";

test.describe("JP-ASK-FAB-03 layout contract", () => {
  test("offsets Ask FAB above dock when AI enabled on mobile", () => {
    const base = computeAskFabBottomPx({
      aiEnabled: true,
      askOpen: false,
      dockOpen: false,
      liftCheckout: false,
      liftFlightCta: false,
    });
    expect(base).toBeGreaterThanOrEqual(18 + FAB_DOCK_SIZE_PX + FAB_GAP_PX);
  });

  test("dock open adds extra offset", () => {
    const closed = computeAskFabBottomPx({
      aiEnabled: true,
      askOpen: false,
      dockOpen: false,
      liftCheckout: false,
      liftFlightCta: false,
    });
    const open = computeAskFabBottomPx({
      aiEnabled: true,
      askOpen: false,
      dockOpen: true,
      liftCheckout: false,
      liftFlightCta: false,
    });
    expect(open).toBeGreaterThan(closed);
  });

  test("ask open resets dock stack offset", () => {
    const closed = computeAskFabBottomPx({
      aiEnabled: true,
      askOpen: false,
      dockOpen: false,
      liftCheckout: false,
      liftFlightCta: false,
    });
    const askOpen = computeAskFabBottomPx({
      aiEnabled: true,
      askOpen: true,
      dockOpen: false,
      liftCheckout: false,
      liftFlightCta: false,
    });
    expect(askOpen).toBeLessThan(closed);
  });
});
