import { mkdirSync, writeFileSync } from "node:fs";
import path from "node:path";
import { test, expect, type Page } from "@playwright/test";

const EVIDENCE_ROOT = path.resolve(__dirname);
const SCREENSHOT_DIR = path.join(EVIDENCE_ROOT, "screenshots");

const VIEWPORTS = [
  { width: 320, height: 720, label: "w320" },
  { width: 390, height: 844, label: "w390" },
  { width: 768, height: 1024, label: "w768" },
  { width: 1024, height: 768, label: "w1024" },
  { width: 1440, height: 900, label: "w1440" },
] as const;

const MODES = [
  { service: "flights" as const, trip: "one_way" as const, slug: "flights-one_way" },
  { service: "flights" as const, trip: "return" as const, slug: "flights-return" },
  { service: "flights" as const, trip: "multi_city" as const, slug: "flights-multi_city" },
  { service: "group" as const, trip: null, slug: "group-ticketing" },
];

type InspectionRecord = {
  file: string;
  viewport: string;
  mode: string;
  overflowX: boolean;
  shellInsideViewport: boolean;
  tripTabsVisible: boolean;
  groupInTripRow: boolean;
  serviceSwitcherOrientation: "rail" | "horizontal" | "unknown";
  tripTabsStyle: "tabs" | "pills" | "unknown";
  notes: string[];
};

type BackdropBox = { x: number; y: number; width: number; height: number };
type BackdropRecord = { mode: string; box: BackdropBox | null };

const inspections: InspectionRecord[] = [];
const backdropAt1024: BackdropRecord[] = [];

async function mockPublicApis(page: Page) {
  await page.route("**/laravel/api/public/content/csrf-token", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ token: "visual-qa-csrf" }),
    });
  });
  await page.route("**/laravel/api/public/content/turnstile-config", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ enabled: false }),
    });
  });
  await page.route("**/laravel/airports/**", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        data: [
          { iata: "ISB", name: "Islamabad International", city: "Islamabad", country: "Pakistan" },
          { iata: "DXB", name: "Dubai International", city: "Dubai", country: "UAE" },
        ],
      }),
    });
  });
  await page.route("**/laravel/groups/search/facets**", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        sectors: [{ value: "JED", label: "KSA — Jeddah" }],
        categories: [{ value: "all", label: "All categories" }],
      }),
    });
  });
  await page.route("**/laravel/api/public/content/homepage", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        source: "cms",
        hero: {
          eyebrow: "Pakistan's trusted OTA",
          headline: "Explore the world with",
          headline_highlight: "JetPakistan",
          subtitle: "Compare flights, pay in PKR, and book with confidence.",
          search_visible: true,
          image: null,
          image_mobile: null,
        },
        trust_chips: [{ label: "PKR pricing" }, { label: "24/7 support" }],
        routes: { enabled: false, items: [] },
        destinations: { enabled: false, items: [] },
        featured_deals: { enabled: false, items: [] },
        why_book: { enabled: false, cards: [] },
        support_cta: { enabled: false },
        feature_board: { enabled: false, items: [] },
      }),
    });
  });
}

async function selectMode(page: Page, mode: (typeof MODES)[number]) {
  if (mode.service === "group") {
    await page.getByTestId("search-service-group").click();
    await expect(page.getByTestId("search-module")).toHaveAttribute("data-search-mode", "group");
    return;
  }
  await page.getByTestId("search-service-flights").click();
  await page.getByTestId(`search-trip-tab-${mode.trip}`).click();
  await expect(page.getByTestId("search-module")).toHaveAttribute("data-search-mode", mode.trip!);
}

async function inspectCapture(
  page: Page,
  viewport: (typeof VIEWPORTS)[number],
  mode: (typeof MODES)[number],
  fileName: string,
): Promise<void> {
  const notes: string[] = [];
  const metrics = await page.evaluate(() => {
    const doc = document.documentElement;
    const overflowX = doc.scrollWidth > doc.clientWidth + 1;
    const switcher = document.querySelector('[data-testid="homepage-service-switcher"]');
    const tripTabs = document.querySelector('[data-testid="search-trip-tabs"]');
    const shell = document.querySelector('[data-testid="homepage-search-shell"]');
    const switcherRect = switcher?.getBoundingClientRect();
    const shellRect = shell?.getBoundingClientRect();
    const tripTabButtons = tripTabs
      ? Array.from(tripTabs.querySelectorAll("button,[role='tab']"))
      : [];
    const groupInTripRow = tripTabButtons.some((el) =>
      /group ticketing/i.test(el.textContent ?? ""),
    );
    const tripTabRadii = tripTabButtons.map((el) => getComputedStyle(el).borderRadius);
    const switcherFlexDir = switcher ? getComputedStyle(switcher).flexDirection : "";
    const switcherOrientation =
      switcherFlexDir === "column"
        ? "rail"
        : switcherFlexDir === "row"
          ? "horizontal"
          : "unknown";
    const avgRadius = tripTabRadii.length
      ? tripTabRadii.reduce((sum, value) => sum + parseFloat(value || "0"), 0) / tripTabRadii.length
      : 0;
    const tripTabsStyle = avgRadius >= 18 ? "pills" : avgRadius > 0 ? "tabs" : "unknown";
    const nextSection = document.querySelector(
      '[data-testid="homepage-public-hero"] ~ section, main section:nth-of-type(2)',
    );
    const nextRect = nextSection?.getBoundingClientRect();
    const shellBottom = shellRect ? shellRect.bottom : 0;
    const nextTop = nextRect ? nextRect.top : Number.POSITIVE_INFINITY;
    const overlap = shellRect && nextRect ? shellBottom > nextTop + 2 : false;
    return {
      overflowX,
      shellInsideViewport: shellRect
        ? shellRect.left >= -1 && shellRect.right <= window.innerWidth + 1
        : false,
      tripTabsVisible: !!tripTabs && tripTabs.checkVisibility(),
      groupInTripRow,
      serviceSwitcherOrientation: switcherOrientation,
      tripTabsStyle,
      overlap,
      viewportWidth: window.innerWidth,
      switcherWidth: switcherRect?.width ?? 0,
      switcherHeight: switcherRect?.height ?? 0,
    };
  });

  if (metrics.overflowX) notes.push("document horizontal overflow detected");
  if (!metrics.shellInsideViewport) notes.push("search shell extends past viewport width");
  if (metrics.groupInTripRow) notes.push("Group Ticketing appears inside trip-type row");
  if (metrics.overlap) notes.push("next section overlaps search shell");
  if (mode.service === "group" && metrics.tripTabsVisible) {
    notes.push("trip tabs still visible in group mode");
  }
  if (viewport.width >= 1024 && metrics.serviceSwitcherOrientation !== "rail") {
    notes.push(`expected vertical service rail at ${viewport.width}px, got ${metrics.serviceSwitcherOrientation}`);
  }
  if (viewport.width < 1024 && metrics.serviceSwitcherOrientation !== "horizontal") {
    notes.push(`expected horizontal service switcher below ${viewport.width}px`);
  }
  if (metrics.tripTabsStyle === "pills") notes.push("trip tabs look pill-shaped (expected tab style)");

  inspections.push({
    file: fileName,
    viewport: viewport.label,
    mode: mode.slug,
    overflowX: metrics.overflowX,
    shellInsideViewport: metrics.shellInsideViewport,
    tripTabsVisible: metrics.tripTabsVisible,
    groupInTripRow: metrics.groupInTripRow,
    serviceSwitcherOrientation: metrics.serviceSwitcherOrientation,
    tripTabsStyle: metrics.tripTabsStyle,
    notes,
  });
}

test.describe.configure({ mode: "serial" });

test.beforeAll(() => {
  mkdirSync(SCREENSHOT_DIR, { recursive: true });
});

test("capture homepage search shell matrix", async ({ page }) => {
  await mockPublicApis(page);

  for (const viewport of VIEWPORTS) {
    await page.setViewportSize({ width: viewport.width, height: viewport.height });
    await page.goto("/", { waitUntil: "load", timeout: 60_000 });
    await expect(page.getByTestId("homepage-hero-backdrop")).toBeVisible();
    await expect(page.getByTestId("homepage-search-shell")).toBeVisible();

    for (const mode of MODES) {
      await selectMode(page, mode);
      await page.waitForTimeout(250);

      const fileName = `${mode.slug}__${viewport.label}.png`;
      const filePath = path.join(SCREENSHOT_DIR, fileName);
      await page.locator('[data-testid="homepage-public-hero"]').screenshot({ path: filePath });
      await inspectCapture(page, viewport, mode, fileName);

      if (viewport.width === 1024) {
        const box = await page.getByTestId("homepage-hero-backdrop").boundingBox();
        backdropAt1024.push({
          mode: mode.slug,
          box: box
            ? { x: box.x, y: box.y, width: box.width, height: box.height }
            : null,
        });
      }
    }
  }
});

test("capture CMS empty hero text (fixtures disabled via route)", async ({ page }) => {
  await page.route("**/laravel/api/public/content/homepage", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        source: "empty",
        hero: {
          eyebrow: "",
          headline: "",
          headline_highlight: "",
          subtitle: "",
          search_visible: true,
        },
      }),
    });
  });
  await mockPublicApis(page);

  await page.setViewportSize({ width: 1024, height: 768 });
  await page.goto("/", { waitUntil: "load", timeout: 60_000 });
  await expect(page.getByTestId("homepage-search-shell")).toBeVisible();

  const heroText = await page.evaluate(() => {
    const hero = document.querySelector('[data-testid="homepage-public-hero"]');
    const title = hero?.querySelector("h1");
    const subtitle = hero?.querySelector("p");
    return {
      hasTitle: !!title && (title.textContent ?? "").trim() !== "",
      hasSubtitle: !!subtitle && (subtitle.textContent ?? "").trim() !== "",
      titleText: title?.textContent?.trim() ?? "",
      subtitleText: subtitle?.textContent?.trim() ?? "",
    };
  });

  const fileName = "cms-empty-hero__w1024.png";
  await page.locator('[data-testid="homepage-public-hero"]').screenshot({
    path: path.join(SCREENSHOT_DIR, fileName),
  });

  writeFileSync(
    path.join(EVIDENCE_ROOT, "cms-empty-hero-metrics.json"),
    JSON.stringify(heroText, null, 2),
    "utf8",
  );
});

test.afterAll(() => {
  const baseline = backdropAt1024[0]?.box;
  const deltas = backdropAt1024.map((entry) => {
    if (!baseline || !entry.box) {
      return { mode: entry.mode, deltaTop: null, deltaWidth: null, deltaHeight: null };
    }
    return {
      mode: entry.mode,
      deltaTop: Math.abs(entry.box.y - baseline.y),
      deltaWidth: Math.abs(entry.box.width - baseline.width),
      deltaHeight: Math.abs(entry.box.height - baseline.height),
    };
  });

  writeFileSync(
    path.join(EVIDENCE_ROOT, "backdrop-bboxes-1024.json"),
    JSON.stringify({ baselineMode: backdropAt1024[0]?.mode ?? null, boxes: backdropAt1024, deltas }, null, 2),
    "utf8",
  );

  writeFileSync(
    path.join(EVIDENCE_ROOT, "inspection-notes.json"),
    JSON.stringify({ generatedAt: new Date().toISOString(), captures: inspections }, null, 2),
    "utf8",
  );
});
