import { expect, type Locator, type Page } from "@playwright/test";
import {
  FAB_GAP_PX,
  FAB_MIN_TOUCH_TARGET_PX,
  rectsOverlap,
} from "../../features/public-floating/public-floating-layout";

export type Rect = { x: number; y: number; width: number; height: number };

export async function getVisualViewport(page: Page) {
  return page.evaluate(() => {
    const vv = window.visualViewport;
    return {
      width: vv?.width ?? window.innerWidth,
      height: vv?.height ?? window.innerHeight,
      offsetTop: vv?.offsetTop ?? 0,
      offsetLeft: vv?.offsetLeft ?? 0,
    };
  });
}

export async function getLocatorRect(locator: Locator): Promise<Rect | null> {
  const box = await locator.boundingBox();
  if (!box) return null;
  return box;
}

export function assertInVisualViewport(rect: Rect, vv: { width: number; height: number; offsetTop: number; offsetLeft: number }) {
  expect(rect.x).toBeGreaterThanOrEqual(-1);
  expect(rect.y).toBeGreaterThanOrEqual(vv.offsetTop - 1);
  expect(rect.x + rect.width).toBeLessThanOrEqual(vv.offsetLeft + vv.width + 1);
  expect(rect.y + rect.height).toBeLessThanOrEqual(vv.offsetTop + vv.height + 1);
}

export function assertMinTouchTarget(rect: Rect, label: string) {
  expect(rect.width, `${label} width`).toBeGreaterThanOrEqual(FAB_MIN_TOUCH_TARGET_PX);
  expect(rect.height, `${label} height`).toBeGreaterThanOrEqual(FAB_MIN_TOUCH_TARGET_PX);
}

export function assertNoOverlap(a: Rect, b: Rect, label: string) {
  const gap = Math.min(
    Math.abs(a.y - (b.y + b.height)),
    Math.abs(b.y - (a.y + a.height)),
  );
  if (rectsOverlap(a, b)) {
    throw new Error(`${label}: FABs overlap (gap=${gap}px, required>=${FAB_GAP_PX}px)`);
  }
  if (gap < FAB_GAP_PX && a.x < b.x + b.width && b.x < a.x + a.width) {
    throw new Error(`${label}: FABs too close vertically (${gap}px < ${FAB_GAP_PX}px)`);
  }
}

export async function scrollToPosition(page: Page, position: "top" | "middle" | "footer") {
  await page.evaluate((pos) => {
    const max = Math.max(document.documentElement.scrollHeight - window.innerHeight, 0);
    const y = pos === "top" ? 0 : pos === "middle" ? max / 2 : max;
    window.scrollTo({ top: y, behavior: "instant" as ScrollBehavior });
  }, position);
  await page.waitForTimeout(150);
}

export { rectsOverlap, FAB_GAP_PX };
