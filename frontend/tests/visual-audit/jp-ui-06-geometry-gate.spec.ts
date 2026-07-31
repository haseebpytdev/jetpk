import { existsSync, readFileSync } from "node:fs";
import path from "node:path";
import { test, expect } from "@playwright/test";

const AUDIT_ROOT = path.resolve(process.cwd(), ".visual-audit", "jp-ui-06");
const GEOMETRY_PATH = path.join(AUDIT_ROOT, "geometry", "homepage-canonical-light-desktop-geometry.json");
const BLUEPRINT_PATH = path.resolve(process.cwd(), "tests/visual-audit/jp-ui-06-blueprint-geometry.json");

const BOX_KEYS: Record<string, string> = {
  "scroll-to-discover": "scrollToDiscover",
  "first-content-section": "firstContentSection",
  "benefit-strip": "benefitStrip",
  "search-panel": "searchPanel",
};

test.describe("JP-UI-06 homepage visible landmark gate", () => {
  test("canonical geometry report exists after capture", () => {
    test.skip(!existsSync(GEOMETRY_PATH), "Run homepage capture before geometry gate");
    expect(existsSync(GEOMETRY_PATH)).toBeTruthy();
  });

  test("visible fold landmarks stay within blueprint y tolerance", () => {
    test.skip(!existsSync(GEOMETRY_PATH), "Run homepage capture before geometry gate");

    const geometry = JSON.parse(readFileSync(GEOMETRY_PATH, "utf8"));
    const blueprint = JSON.parse(readFileSync(BLUEPRINT_PATH, "utf8"));
    const landmarks = blueprint.landmarks.filter(
      (landmark: { page: string; compareYOnly?: boolean }) =>
        landmark.page === "homepage" && landmark.compareYOnly,
    );

    expect(landmarks.length).toBeGreaterThanOrEqual(2);

    for (const landmark of landmarks) {
      const key = BOX_KEYS[landmark.element];
      const box = geometry.boxes?.[key];
      expect(box, `Missing geometry box for ${landmark.element}`).toBeTruthy();
      const delta = Math.abs(box.y - landmark.y);
      expect(delta, `${landmark.element} y=${box.y} vs blueprint ${landmark.y}`).toBeLessThanOrEqual(
        landmark.tolerance,
      );
    }
  });

  test("accepted hero/search landmarks remain within tolerance", () => {
    test.skip(!existsSync(GEOMETRY_PATH), "Run homepage capture before geometry gate");

    const geometry = JSON.parse(readFileSync(GEOMETRY_PATH, "utf8"));
    const blueprint = JSON.parse(readFileSync(BLUEPRINT_PATH, "utf8"));
    const preserved = ["search-panel", "benefit-strip"];

    for (const element of preserved) {
      const landmark = blueprint.landmarks.find(
        (entry: { page: string; element: string }) => entry.page === "homepage" && entry.element === element,
      );
      const box = geometry.boxes?.[BOX_KEYS[element]];
      expect(landmark).toBeTruthy();
      expect(box).toBeTruthy();
      const tol = landmark.tolerance ?? 2;
      expect(Math.abs(box.x - landmark.x)).toBeLessThanOrEqual(tol);
      expect(Math.abs(box.y - landmark.y)).toBeLessThanOrEqual(tol);
      expect(Math.abs(box.width - landmark.width)).toBeLessThanOrEqual(tol);
      expect(Math.abs(box.height - landmark.height)).toBeLessThanOrEqual(tol);
    }
  });
});
