import { test, expect } from "@playwright/test";
import { readFileSync, mkdirSync, writeFileSync } from "node:fs";
import path from "node:path";

const HOMEPAGE_PATH = "/__dev/jetpk-homepage-v2?capture=1";
const OUTPUT_DIR = ".visual-audit/jp-public-next-theme-03b";
const CANONICAL_VIEWPORT = { width: 1122, height: 1330 };

const LANDMARKS = [
  "header",
  "hero",
  "search",
  "benefits",
  "discover",
  "destinations",
  "offers",
  "why",
  "support",
  "inspiration",
  "footer",
] as const;

const CAPTURE_SETTINGS = {
  viewport: CANONICAL_VIEWPORT,
  deviceScaleFactor: 1,
  browserZoom: "100%",
  fullPage: false,
  animationsDisabled: true,
};

const RESPONSIVE = [
  { name: "homepage-1440-light", viewport: { width: 1440, height: 1200 }, theme: "light" as const },
  { name: "homepage-1440-dark", viewport: { width: 1440, height: 1200 }, theme: "dark" as const },
  { name: "homepage-768-light", viewport: { width: 768, height: 1024 }, theme: "light" as const },
  { name: "homepage-768-dark", viewport: { width: 768, height: 1024 }, theme: "dark" as const },
  { name: "homepage-390-light", viewport: { width: 390, height: 844 }, theme: "light" as const },
  { name: "homepage-390-dark", viewport: { width: 390, height: 844 }, theme: "dark" as const },
];

async function prepareDeterministicCapture(page: import("@playwright/test").Page) {
  await page.addStyleTag({
    content: `*, *::before, *::after { animation-duration: 0s !important; transition-duration: 0s !important; }`,
  });
  await page.evaluate(async () => {
    await document.fonts.ready;
    await Promise.all(
      Array.from(document.images)
        .filter((img) => !img.complete)
        .map(
          (img) =>
            new Promise<void>((resolve) => {
              img.onload = () => resolve();
              img.onerror = () => resolve();
            }),
        ),
    );
    await new Promise((r) => requestAnimationFrame(() => requestAnimationFrame(r)));
  });
}

test.beforeAll(async ({ request }) => {
  expect((await request.get(HOMEPAGE_PATH, { timeout: 120_000 })).ok()).toBeTruthy();
});

test.use({ deviceScaleFactor: 1 });

test("capture canonical desktop light", async ({ page }) => {
  await page.setViewportSize(CANONICAL_VIEWPORT);
  await page.goto(HOMEPAGE_PATH, { waitUntil: "networkidle" });
  await prepareDeterministicCapture(page);

  await expect(page.locator('[data-landmark="header"]')).toBeVisible();
  const headerBox = await page.locator('[data-landmark="header"]').boundingBox();
  expect(headerBox?.y ?? 99).toBeLessThan(2);

  await page.screenshot({
    path: `${OUTPUT_DIR}/homepage-canonical-light.png`,
    fullPage: false,
  });

  const geometryPayload = await page.evaluate((landmarkNames) => {
    const measure = (name: string) => {
      const el = document.querySelector(`[data-landmark="${name}"]`);
      if (!el) return null;
      const rect = el.getBoundingClientRect();
      return {
        x: Math.round(rect.x),
        y: Math.round(rect.y + window.scrollY),
        width: Math.round(rect.width),
        height: Math.round(rect.height),
        right: Math.round(rect.right),
        bottom: Math.round(rect.bottom + window.scrollY),
      };
    };

    const landmarks: Record<string, ReturnType<typeof measure>> = {};
    for (const name of landmarkNames) {
      landmarks[name] = measure(name);
    }

    const overflowLandmarks = landmarkNames.map((name) => {
      const el = document.querySelector(`[data-landmark="${name}"]`);
      if (!el) return { region: name, left: null, right: null };
      const rect = el.getBoundingClientRect();
      const style = window.getComputedStyle(el);
      return {
        region: name,
        left: Math.round(rect.left),
        right: Math.round(rect.right),
        overflowX: style.overflowX,
      };
    });

    const clippingSections = landmarkNames.map((name) => {
      const el = document.querySelector(`[data-landmark="${name}"]`);
      if (!el) return { region: name, clientHeight: 0, scrollHeight: 0, hiddenRequired: true };
      const style = window.getComputedStyle(el);
      return {
        region: name,
        clientHeight: (el as HTMLElement).clientHeight,
        scrollHeight: (el as HTMLElement).scrollHeight,
        hiddenRequired: style.display === "none",
      };
    });

    return {
      landmarks,
      pageHeight: document.documentElement.scrollHeight,
      bodyScrollHeight: document.body.scrollHeight,
      scrollWidth: document.documentElement.scrollWidth,
      overflowAudit: {
        scrollWidth: document.documentElement.scrollWidth,
        clientWidth: document.documentElement.clientWidth,
        landmarks: overflowLandmarks,
      },
      clippingAudit: { sections: clippingSections },
    };
  }, [...LANDMARKS]);

  const geomDir = path.join(process.cwd(), OUTPUT_DIR, "geometry");
  mkdirSync(geomDir, { recursive: true });

  writeFileSync(
    path.join(geomDir, "implementation-geometry.json"),
    JSON.stringify(geometryPayload, null, 2),
  );

  writeFileSync(
    path.join(geomDir, "capture-meta.json"),
    JSON.stringify(
      {
        ...CAPTURE_SETTINGS,
        capturedAt: new Date().toISOString(),
        route: HOMEPAGE_PATH,
      },
      null,
      2,
    ),
  );
});

for (const scenario of RESPONSIVE) {
  test(`capture ${scenario.name}`, async ({ page }) => {
    await page.setViewportSize(scenario.viewport);
    await page.goto(HOMEPAGE_PATH, { waitUntil: "networkidle" });
    if (scenario.theme === "dark") {
      await page.getByTestId("jp-hp-theme-toggle").click();
      await expect(page.getByTestId("jp-theme-v2-root")).toHaveAttribute("data-jp-theme", "dark");
    }
    await page.screenshot({
      path: `${OUTPUT_DIR}/${scenario.name}.png`,
      fullPage: true,
    });
  });
}

test("build contact sheet", async ({ page }) => {
  const shots = [
    "homepage-canonical-light.png",
    "homepage-1440-light.png",
    "homepage-768-light.png",
    "homepage-390-light.png",
  ];
  const { PNG } = await import("pngjs");
  const fs = await import("node:fs");
  const images = shots
    .map((name) => path.join(process.cwd(), OUTPUT_DIR, name))
    .filter((p) => fs.existsSync(p))
    .map((p) => PNG.sync.read(fs.readFileSync(p)));

  if (images.length === 0) return;

  const thumbWidth = 360;
  const thumbs = images.map((img) => {
    const scale = thumbWidth / img.width;
    const h = Math.round(img.height * scale);
    const out = new PNG({ width: thumbWidth, height: h });
    for (let y = 0; y < h; y++) {
      for (let x = 0; x < thumbWidth; x++) {
        const sx = Math.min(img.width - 1, Math.round(x / scale));
        const sy = Math.min(img.height - 1, Math.round(y / scale));
        const si = (img.width * sy + sx) * 4;
        const di = (thumbWidth * y + x) * 4;
        out.data[di] = img.data[si];
        out.data[di + 1] = img.data[si + 1];
        out.data[di + 2] = img.data[si + 2];
        out.data[di + 3] = 255;
      }
    }
    return out;
  });

  const rowHeight = Math.max(...thumbs.map((t) => t.height));
  const sheet = new PNG({ width: thumbWidth * thumbs.length, height: rowHeight });
  thumbs.forEach((thumb, index) => {
    for (let y = 0; y < thumb.height; y++) {
      for (let x = 0; x < thumb.width; x++) {
        const si = (thumb.width * y + x) * 4;
        const di = (sheet.width * y + (index * thumbWidth + x)) * 4;
        sheet.data[di] = thumb.data[si];
        sheet.data[di + 1] = thumb.data[si + 1];
        sheet.data[di + 2] = thumb.data[si + 2];
        sheet.data[di + 3] = 255;
      }
    }
  });

  mkdirSync(path.join(process.cwd(), OUTPUT_DIR, "compare"), { recursive: true });
  fs.writeFileSync(
    path.join(process.cwd(), OUTPUT_DIR, "compare", "contact-sheet.png"),
    PNG.sync.write(sheet),
  );
  await page.goto(HOMEPAGE_PATH);
});
