import { expect, test, type Page } from "@playwright/test";
import { resultsQuery, setupScenarioMocks } from "./visual-audit/jp-ui-01-fixtures";
import { setupJpUi04aScenario } from "./visual-audit/jp-ui-04a-fixtures";

const VIEWPORTS = [
  { name: "320", width: 320, height: 720 },
  { name: "360", width: 360, height: 740 },
  { name: "390", width: 390, height: 844 },
  { name: "430", width: 430, height: 932 },
  { name: "768", width: 768, height: 1024 },
  { name: "1023", width: 1023, height: 900 },
  { name: "1024", width: 1024, height: 900 },
  { name: "1440", width: 1440, height: 900 },
] as const;

const RESULTS = `/flights/results?${resultsQuery()}`;
const PASSENGERS = "/booking/passengers";
const REVIEW = "/booking/review";
const RETURN = `/flights/return-options?search_id=jp-ui-01-audit-search-id&outbound_key=outbound-1`;

async function enableForceAi(page: Page) {
  await page.context().addCookies([
    {
      name: "jp_playwright_force_ai",
      value: "1",
      url: process.env.PLAYWRIGHT_BASE_URL ?? "http://127.0.0.1:3002",
    },
  ]);
}

async function waitForAskFab(page: Page) {
  await expect(page.getByTestId("ask-jetpakistan-fab")).toBeVisible({ timeout: 15_000 });
}

async function assertNoHorizontalOverflow(page: Page) {
  const metrics = await page.evaluate(() => ({
    docScroll: document.documentElement.scrollWidth,
    docClient: document.documentElement.clientWidth,
    bodyScroll: document.body.scrollWidth,
    inner: window.innerWidth,
  }));
  expect(
    metrics.docScroll,
    `horizontal overflow scroll=${metrics.docScroll} client=${metrics.docClient}`,
  ).toBeLessThanOrEqual(metrics.docClient + 1);
}

async function measureFloating(page: Page) {
  return page.evaluate(() => {
    const ask = document.querySelector('[data-testid="ask-jetpakistan-fab"]') as HTMLElement | null;
    const dock = document.querySelector('[data-testid="public-fab-trigger"]') as HTMLElement | null;
    const dockRoot = document.querySelector('[data-testid="public-fab-dock"]') as HTMLElement | null;
    const panel = document.querySelector(".jp-public-fab-panel") as HTMLElement | null;
    const askPanel = document.querySelector('[data-testid="ask-jetpakistan-panel"]') as HTMLElement | null;
    const sticky = document.querySelector('[data-testid="mobile-sticky-action"]') as HTMLElement | null;
    const details = document.querySelector('[data-testid="flight-details-trigger"]') as HTMLElement | null;
    const book = document.querySelector('[data-testid="book-now-trigger"]') as HTMLElement | null;
    const card = document.querySelector('[data-testid="flight-result-card"]') as HTMLElement | null;
    const rect = (el: HTMLElement | null) => {
      if (!el) return null;
      const styles = window.getComputedStyle(el);
      if (styles.visibility === "hidden" || styles.display === "none" || styles.opacity === "0") {
        return null;
      }
      const r = el.getBoundingClientRect();
      if (r.width <= 0 || r.height <= 0) return null;
      return {
        top: r.top,
        left: r.left,
        right: r.right,
        bottom: r.bottom,
        width: r.width,
        height: r.height,
      };
    };
    return {
      ask: rect(ask),
      dock: rect(dock),
      dockRoot: rect(dockRoot),
      panel: dockRoot && (dockRoot as HTMLDetailsElement).open ? rect(panel) : null,
      askPanel: rect(askPanel),
      sticky: rect(sticky),
      details: rect(details),
      book: rect(book),
      cardWidth: card ? card.getBoundingClientRect().width : null,
      askBottomVar: getComputedStyle(document.documentElement).getPropertyValue("--jp-ask-fab-bottom").trim(),
      dockBottomVar: getComputedStyle(document.documentElement).getPropertyValue("--jp-dock-fab-bottom").trim(),
      dockOpen: document.documentElement.dataset.jpDockOpen === "1",
      askOpen: document.documentElement.dataset.jpAskOpen === "1",
      subLg: document.documentElement.dataset.jpFabSubLg === "1",
      viewport: { w: window.innerWidth, h: window.innerHeight },
      overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
    };
  });
}

function assertNoIntersect(
  label: string,
  a: { top: number; left: number; right: number; bottom: number } | null,
  b: { top: number; left: number; right: number; bottom: number } | null,
) {
  if (!a || !b) return;
  const w = Math.min(a.right, b.right) - Math.max(a.left, b.left);
  const h = Math.min(a.bottom, b.bottom) - Math.max(a.top, b.top);
  expect(w > 0 && h > 0, `${label} intersection ${w}x${h}`).toBeFalsy();
}

test.describe("JP-UI-PERF-01C floating layout collision suite", () => {
  test.beforeAll(async ({ request }) => {
    expect((await request.get("/", { timeout: 120_000 })).ok()).toBeTruthy();
  });

  for (const vp of VIEWPORTS) {
    test(`viewport ${vp.name}: home + login floating safety`, async ({ page }) => {
      await enableForceAi(page);
      await page.setViewportSize({ width: vp.width, height: vp.height });

      for (const path of ["/", "/login"] as const) {
        await page.goto(path, { waitUntil: "domcontentloaded", timeout: 60_000 });
        await expect(page.locator("#main-content, main").first()).toBeVisible();
        await waitForAskFab(page);
        await assertNoHorizontalOverflow(page);

        const m = await measureFloating(page);
        expect(m.overflow).toBeLessThanOrEqual(1);

        if (vp.width < 1024) {
          expect(m.dock).not.toBeNull();
          expect(m.ask).not.toBeNull();
          assertNoIntersect("ask vs dock", m.ask, m.dock);
          if (m.ask && m.dock) {
            expect(m.ask.bottom).toBeLessThanOrEqual(m.dock.top + 0.5);
          }
        } else {
          expect(m.dock).toBeNull();
          expect(m.ask).not.toBeNull();
          // Desktop must not reserve lifted dock stack (~178px).
          if (m.ask) {
            expect(m.ask.bottom).toBeGreaterThan(vp.height - 120);
          }
        }
      }
    });

    test(`viewport ${vp.name}: flights results closed FABs`, async ({ page }) => {
      await enableForceAi(page);
      await setupJpUi04aScenario(page, {
        id: `01c-results-${vp.name}`,
        family: "results",
        route: RESULTS,
        theme: "light",
        viewport: { name: vp.name, width: vp.width, height: vp.height },
        zoom: 1,
        state: "present",
        fixtureId: "results-present",
        waitForTestId: "flight-result-card",
      });
      await page.setViewportSize({ width: vp.width, height: vp.height });
      await page.goto(RESULTS, { waitUntil: "load", timeout: 60_000 });
      await expect(page.getByTestId("flight-result-card").first()).toBeVisible({ timeout: 30_000 });
      await waitForAskFab(page);

      const cardWidth = await page.getByTestId("flight-result-card").first().evaluate((el) => el.getBoundingClientRect().width);
      const m = await measureFloating(page);
      expect(m.overflow).toBeLessThanOrEqual(1);

      if (vp.width < 1024) {
        expect(m.ask).not.toBeNull();
        expect(m.dock).not.toBeNull();
        assertNoIntersect("ask vs dock lifted", m.ask, m.dock);
        assertNoIntersect("ask vs details", m.ask, m.details);
        assertNoIntersect("ask vs book", m.ask, m.book);
        assertNoIntersect("dock vs details", m.dock, m.details);
        assertNoIntersect("dock vs book", m.dock, m.book);
        if (m.ask && m.dock) {
          expect(m.ask.bottom + 13.5).toBeLessThanOrEqual(m.dock.top + 0.5);
        }
      } else {
        expect(m.dock).toBeNull();
        expect(m.ask).not.toBeNull();
      }

      // Card width stability checkpoint within this run (no layout shrink from floating fix).
      expect(Math.abs(cardWidth - (m.cardWidth ?? cardWidth))).toBeLessThanOrEqual(1);
    });
  }

  test("lifted booking passengers: Ask stacks above dock; sticky clear", async ({ page }) => {
    await enableForceAi(page);
    await setupScenarioMocks(page, "passengers");
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(PASSENGERS, { waitUntil: "load", timeout: 60_000 });
    await expect(page.locator("#main-content, main").first()).toBeVisible();
    await waitForAskFab(page);

    const m = await measureFloating(page);
    expect(m.ask).not.toBeNull();
    expect(m.dock).not.toBeNull();
    assertNoIntersect("ask vs dock", m.ask, m.dock);
    assertNoIntersect("ask vs sticky", m.ask, m.sticky);
    assertNoIntersect("dock vs sticky", m.dock, m.sticky);
    if (m.ask && m.dock) {
      expect(m.ask.bottom + 13.5).toBeLessThanOrEqual(m.dock.top + 0.5);
    }
  });

  test("dock open hides Ask FAB; Ask open hides dock", async ({ page }) => {
    await enableForceAi(page);
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto("/", { waitUntil: "domcontentloaded" });
    await expect(page.getByTestId("ask-jetpakistan-fab")).toBeVisible();
    await expect(page.getByTestId("public-fab-trigger")).toBeVisible();

    await page.getByTestId("public-fab-trigger").click();
    await expect(page.locator('[data-testid="public-fab-dock"][open]')).toBeVisible();
    await expect(page.getByTestId("ask-jetpakistan-fab")).toHaveCount(0);

    const panel = page.locator(".jp-public-fab-panel");
    const panelBox = await panel.boundingBox();
    expect(panelBox).not.toBeNull();
    if (panelBox) {
      expect(panelBox.x).toBeGreaterThanOrEqual(-1);
      expect(panelBox.x + panelBox.width).toBeLessThanOrEqual(390 + 1);
      expect(panelBox.y).toBeGreaterThanOrEqual(-1);
      expect(panelBox.y + panelBox.height).toBeLessThanOrEqual(844 + 1);
    }

    await page.keyboard.press("Escape");
    await expect(page.getByTestId("ask-jetpakistan-fab")).toBeVisible();

    await page.getByTestId("ask-jetpakistan-fab").click();
    await expect(page.getByTestId("ask-jetpakistan-panel")).toBeVisible();
    await expect(page.getByTestId("public-fab-dock")).toBeHidden();
    await page.keyboard.press("Escape");
    await expect(page.getByTestId("ask-jetpakistan-panel")).toHaveCount(0);
  });

  test("review + return-options fixture smoke at 390", async ({ page }) => {
    await enableForceAi(page);
    await setupScenarioMocks(page, "review");
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(REVIEW, { waitUntil: "load", timeout: 60_000 });
    const review = await measureFloating(page);
    assertNoIntersect("review ask vs dock", review.ask, review.dock);
    await assertNoHorizontalOverflow(page);

    await page.route("**/laravel/flights/return-options/data*", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          success: true,
          search_id: "jp-ui-01-audit-search-id",
          outbound_key: "outbound-1",
          return_options: [],
          empty_message: "No return flights",
        }),
      });
    });
    await page.goto(RETURN, { waitUntil: "load", timeout: 60_000 });
    const ret = await measureFloating(page);
    assertNoIntersect("return ask vs dock", ret.ask, ret.dock);
    await assertNoHorizontalOverflow(page);
  });

  test("groups booking review fixture path at 390", async ({ page }) => {
    await enableForceAi(page);
    await page.setViewportSize({ width: 390, height: 844 });
    await page.route("**/laravel/groups/booking/**", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({ success: true }),
      });
    });
    await page.goto("/groups/booking/GRP-TEST/review", { waitUntil: "domcontentloaded", timeout: 60_000 });
    await assertNoHorizontalOverflow(page);
    const m = await measureFloating(page);
    if (m.ask && m.dock) {
      assertNoIntersect("groups ask vs dock", m.ask, m.dock);
    }
  });
});
