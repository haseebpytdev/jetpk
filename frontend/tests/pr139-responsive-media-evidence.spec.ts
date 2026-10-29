import { expect, test, type Page, type Request } from "@playwright/test";
import fs from "node:fs";
import path from "node:path";

const EVIDENCE_DIR = path.resolve(
  process.cwd(),
  "../docs/evidence/jp-ui-perf-stability-01a-media",
);
const NETWORK_JSON = path.join(EVIDENCE_DIR, "08-network-evidence.json");
const HTML_SNIPPET = path.join(EVIDENCE_DIR, "08-initial-document-hero-preload.html");

const VIEWPORTS = [
  { name: "390", width: 390, height: 844 },
  { name: "768", width: 768, height: 1024 },
  { name: "1440", width: 1440, height: 900 },
] as const;

type Capture = {
  viewport: string;
  hero: Record<string, unknown>;
  destination: Record<string, unknown>;
  route: Record<string, unknown>;
  featuredDeal: Record<string, unknown>;
  support: Record<string, unknown>;
  imageRequests: Array<{
    url: string;
    resourceType: string;
    contentType?: string | null;
    transferredBytes?: number | null;
  }>;
  assertions: Record<string, string | boolean | number>;
};

function isEvidenceImage(url: string): boolean {
  return url.includes("/_evidence/pr139-responsive/");
}

function isOriginal(url: string): boolean {
  return /hero-original|card-original/.test(url);
}

function isDerivative(url: string): boolean {
  return isEvidenceImage(url) && !isOriginal(url);
}

function formatFromUrl(url: string): string {
  if (url.includes(".avif")) return "AVIF";
  if (url.includes(".webp")) return "WEBP";
  if (url.includes(".jpg") || url.includes(".jpeg")) return "JPEG";
  return "UNKNOWN";
}

async function readPictureState(page: Page, testId: string) {
  return page.locator(`[data-testid="${testId}"]`).evaluate((root) => {
    const picture = root.matches("picture")
      ? (root as HTMLPictureElement)
      : root.querySelector("picture");
    const img = (picture?.querySelector("img") ?? root.querySelector("img")) as HTMLImageElement | null;
    const sources = Array.from(picture?.querySelectorAll("source") ?? []).map((source) => ({
      type: source.getAttribute("type"),
      media: source.getAttribute("media"),
      srcset: source.getAttribute("srcset"),
      sizes: source.getAttribute("sizes"),
    }));
    return {
      currentSrc: img?.currentSrc ?? "",
      src: img?.src ?? "",
      loading: img?.getAttribute("loading"),
      sources,
    };
  });
}

async function captureViewport(page: Page, name: string, width: number, height: number): Promise<Capture> {
  const imageRequests: Capture["imageRequests"] = [];
  const onRequestFinished = async (req: Request) => {
    if (req.resourceType() !== "image") return;
    const url = req.url();
    if (!isEvidenceImage(url)) return;
    const response = await req.response();
    let transferredBytes: number | null = null;
    try {
      // Prefer network transfer size (0 when served from cache / reused).
      const sizes = await req.sizes();
      transferredBytes = sizes.responseBodySize;
    } catch {
      transferredBytes = null;
    }
    imageRequests.push({
      url,
      resourceType: req.resourceType(),
      contentType: response?.headers()["content-type"] ?? null,
      transferredBytes,
    });
  };

  page.on("requestfinished", onRequestFinished);

  await page.setViewportSize({ width, height });

  // Capture initial document via request API (avoids consuming navigation body).
  const initialDoc = await page.request.get("/");
  expect(initialDoc.ok()).toBeTruthy();
  const initialHtml = await initialDoc.text();
  if (name === "1440") {
    fs.mkdirSync(EVIDENCE_DIR, { recursive: true });
    const preloadMatch =
      initialHtml.match(/<link[^>]+rel=["']preload["'][^>]*>/gi)?.join("\n") ??
      "NO_PRELOAD_LINKS";
    fs.writeFileSync(HTML_SNIPPET, preloadMatch, "utf8");
  }

  const hasHeroPreload =
    /rel=["']preload["'][^>]*as=["']image["']/i.test(initialHtml) ||
    /as=["']image["'][^>]*rel=["']preload["']/i.test(initialHtml) ||
    /rel=["']preload["'][^>]*href=["'][^"']*hero-(desktop|mobile)/i.test(initialHtml);
  const preloadPointsAtDerivative =
    /\/_evidence\/pr139-responsive\/hero-(desktop|mobile|tablet)\.(avif|webp|jpg)/i.test(
      initialHtml,
    );

  const response = await page.goto("/", { waitUntil: "domcontentloaded" });
  expect(response?.ok()).toBeTruthy();
  await page.locator('[data-testid="homepage-public-hero"]').waitFor({ state: "attached", timeout: 15_000 });
  await page.locator('[data-testid="homepage-hero-responsive-picture"]').waitFor({
    state: "attached",
    timeout: 15_000,
  });

  // SectionContainer does not forward data-testid; use card testids (always attached).
  await page.locator('[data-testid="route-card"]').first().waitFor({ state: "attached", timeout: 15_000 });
  await page.locator('[data-testid="route-card"]').first().scrollIntoViewIfNeeded();
  await page.locator('[data-testid="destination-card"]').first().scrollIntoViewIfNeeded();
  await page.locator('[data-testid="featured-offer-card"]').first().scrollIntoViewIfNeeded();
  if (width >= 1024) {
    await page.locator('[data-media-slot="support-callout-illustration"]').scrollIntoViewIfNeeded();
  }
  await page.waitForTimeout(800);

  const hero = await readPictureState(page, "homepage-hero-responsive-picture");
  const routePicture = page.locator('[data-testid="route-card"] picture').first();
  const destinationPicture = page.locator('[data-testid="destination-card"] picture').first();
  const featuredPicture = page.locator('[data-testid="featured-offer-card"] picture').first();
  const supportPicture = page.locator('[data-media-slot="support-callout-illustration"] picture').first();

  const route = await routePicture.evaluate((picture) => {
    const img = picture.querySelector("img") as HTMLImageElement | null;
    return {
      currentSrc: img?.currentSrc ?? "",
      src: img?.src ?? "",
      loading: img?.getAttribute("loading"),
      sources: Array.from(picture.querySelectorAll("source")).map((source) => ({
        type: source.getAttribute("type"),
        media: source.getAttribute("media"),
        srcset: source.getAttribute("srcset"),
        sizes: source.getAttribute("sizes"),
      })),
    };
  });
  const destination = await destinationPicture.evaluate((picture) => {
    const img = picture.querySelector("img") as HTMLImageElement | null;
    return {
      currentSrc: img?.currentSrc ?? "",
      src: img?.src ?? "",
      loading: img?.getAttribute("loading"),
      sources: Array.from(picture.querySelectorAll("source")).map((source) => ({
        type: source.getAttribute("type"),
        media: source.getAttribute("media"),
        srcset: source.getAttribute("srcset"),
        sizes: source.getAttribute("sizes"),
      })),
    };
  });
  const featuredDeal = await featuredPicture.evaluate((picture) => {
    const img = picture.querySelector("img") as HTMLImageElement | null;
    return {
      currentSrc: img?.currentSrc ?? "",
      src: img?.src ?? "",
      loading: img?.getAttribute("loading"),
      sources: Array.from(picture.querySelectorAll("source")).map((source) => ({
        type: source.getAttribute("type"),
        media: source.getAttribute("media"),
        srcset: source.getAttribute("srcset"),
        sizes: source.getAttribute("sizes"),
      })),
    };
  });

  let support: Record<string, unknown> = {
    currentSrc: "",
    src: "",
    loading: null,
    sources: [],
    skipped: width < 1024,
  };
  if (width >= 1024 && (await supportPicture.count()) > 0) {
    support = await supportPicture.evaluate((picture) => {
      const img = picture.querySelector("img") as HTMLImageElement | null;
      return {
        currentSrc: img?.currentSrc ?? "",
        src: img?.src ?? "",
        loading: img?.getAttribute("loading"),
        sources: Array.from(picture.querySelectorAll("source")).map((source) => ({
          type: source.getAttribute("type"),
          media: source.getAttribute("media"),
          srcset: source.getAttribute("srcset"),
          sizes: source.getAttribute("sizes"),
        })),
      };
    });
  }

  const heroRequests = imageRequests.filter((item) => item.url.includes("/hero-"));
  const uniqueHero = new Set(heroRequests.map((item) => item.url.split("?")[0]));
  const heroNonZeroEvents = heroRequests.filter(
    (item) => typeof item.transferredBytes === "number" && item.transferredBytes > 0,
  );
  const heroZeroByte = heroRequests.filter((item) => item.transferredBytes === 0);
  // Unique hero URLs that incurred at least one non-zero body transfer.
  const heroNonZeroUrls = new Set(heroNonZeroEvents.map((item) => item.url.split("?")[0]));
  const mobileAndDesktopBothTransferred =
    [...heroNonZeroUrls].some((url) => url.includes("hero-mobile.")) &&
    [...heroNonZeroUrls].some((url) => url.includes("hero-desktop."));

  const supportUrls = imageRequests
    .map((item) => item.url)
    .filter((url) => url.includes("/support-"));
  const uniqueSupport = new Set(supportUrls.map((url) => url.split("?")[0]));
  const supportDup =
    [...uniqueSupport].some((url) => url.includes("support-mobile.")) &&
    [...uniqueSupport].some((url) => url.includes("support-desktop."));

  const assertions = {
    HERO_PRELOAD_INITIAL_DOCUMENT: hasHeroPreload && preloadPointsAtDerivative ? "YES" : "NO",
    HERO_BROWSER_DERIVATIVE_SELECTED: isDerivative(String(hero.currentSrc)) ? "YES" : "NO",
    HERO_SELECTED_FORMAT: formatFromUrl(String(hero.currentSrc)),
    HERO_SELECTED_URL_IS_DERIVATIVE: isDerivative(String(hero.currentSrc)) ? "YES" : "NO",
    HERO_ORIGINAL_MULTI_MB_SOURCE_SELECTED: isOriginal(String(hero.currentSrc)) ? "YES" : "NO",
    HERO_UNIQUE_URL_COUNT: uniqueHero.size,
    HERO_NONZERO_TRANSFER_COUNT: heroNonZeroUrls.size,
    HERO_NONZERO_RESPONSE_EVENTS: heroNonZeroEvents.length,
    HERO_ZERO_BYTE_CACHE_OR_REUSE_EVENTS: heroZeroByte.length,
    // Duplicate cost = more than one distinct hero URL with a non-zero transfer.
    HERO_DUPLICATE_TRANSFER: heroNonZeroUrls.size <= 1 ? "NO" : "YES",
    HERO_DUPLICATE_DOWNLOAD: heroNonZeroUrls.size <= 1 ? "NO" : "YES",
    MOBILE_AND_DESKTOP_HERO_BOTH_TRANSFERRED: mobileAndDesktopBothTransferred ? "YES" : "NO",
    MOBILE_AND_DESKTOP_HERO_BOTH_DOWNLOADED: mobileAndDesktopBothTransferred ? "YES" : "NO",
    DESTINATION_BROWSER_DERIVATIVE_SELECTED: isDerivative(String(destination.currentSrc))
      ? "YES"
      : "NO",
    ROUTE_BROWSER_DERIVATIVE_SELECTED: isDerivative(String(route.currentSrc)) ? "YES" : "NO",
    FEATURED_DEAL_BROWSER_DERIVATIVE_SELECTED: isDerivative(String(featuredDeal.currentSrc))
      ? "YES"
      : "NO",
    SUPPORT_BROWSER_DERIVATIVE_SELECTED:
      width < 1024
        ? "N/A_HIDDEN_BELOW_LG"
        : isDerivative(String(support.currentSrc))
          ? "YES"
          : "NO",
    SUPPORT_DUPLICATE_DESKTOP_MOBILE_DOWNLOAD: supportDup ? "YES" : "NO",
    GIANT_ORIGINAL_SELECTED_WHEN_DERIVATIVE_AVAILABLE: [
      hero.currentSrc,
      destination.currentSrc,
      route.currentSrc,
      featuredDeal.currentSrc,
      support.currentSrc,
    ].some((url) => isOriginal(String(url)))
      ? "YES"
      : "NO",
    BELOW_FOLD_LAZY_LOADING_PRESERVED:
      route.loading === "lazy" &&
      destination.loading === "lazy" &&
      featuredDeal.loading === "lazy"
        ? "YES"
        : "NO",
    HERO_REQUEST_COUNT: uniqueHero.size,
  };

  page.off("requestfinished", onRequestFinished);

  return {
    viewport: name,
    hero,
    destination,
    route,
    featuredDeal,
    support,
    imageRequests,
    assertions,
  };
}

test("PR139 responsive media browser evidence", async ({ page }) => {
  const captures: Capture[] = [];

  for (const viewport of VIEWPORTS) {
    const capture = await captureViewport(page, viewport.name, viewport.width, viewport.height);
    captures.push(capture);

    expect(capture.assertions.HERO_PRELOAD_INITIAL_DOCUMENT, `vp ${viewport.name}`).toBe("YES");
    expect(capture.assertions.HERO_BROWSER_DERIVATIVE_SELECTED, `vp ${viewport.name}`).toBe("YES");
    expect(capture.assertions.HERO_ORIGINAL_MULTI_MB_SOURCE_SELECTED, `vp ${viewport.name}`).toBe(
      "NO",
    );
    expect(capture.assertions.HERO_UNIQUE_URL_COUNT, `vp ${viewport.name}`).toBe(1);
    expect(capture.assertions.HERO_NONZERO_TRANSFER_COUNT, `vp ${viewport.name}`).toBe(1);
    expect(capture.assertions.HERO_DUPLICATE_TRANSFER, `vp ${viewport.name}`).toBe("NO");
    expect(capture.assertions.HERO_DUPLICATE_DOWNLOAD, `vp ${viewport.name}`).toBe("NO");
    expect(capture.assertions.MOBILE_AND_DESKTOP_HERO_BOTH_TRANSFERRED, `vp ${viewport.name}`).toBe(
      "NO",
    );
    expect(capture.assertions.MOBILE_AND_DESKTOP_HERO_BOTH_DOWNLOADED, `vp ${viewport.name}`).toBe(
      "NO",
    );
    expect(capture.assertions.DESTINATION_BROWSER_DERIVATIVE_SELECTED, `vp ${viewport.name}`).toBe(
      "YES",
    );
    expect(capture.assertions.ROUTE_BROWSER_DERIVATIVE_SELECTED, `vp ${viewport.name}`).toBe("YES");
    expect(capture.assertions.FEATURED_DEAL_BROWSER_DERIVATIVE_SELECTED, `vp ${viewport.name}`).toBe(
      "YES",
    );
    expect(capture.assertions.GIANT_ORIGINAL_SELECTED_WHEN_DERIVATIVE_AVAILABLE, `vp ${viewport.name}`).toBe(
      "NO",
    );
    expect(capture.assertions.BELOW_FOLD_LAZY_LOADING_PRESERVED, `vp ${viewport.name}`).toBe("YES");
    if (viewport.width >= 1024) {
      expect(capture.assertions.SUPPORT_BROWSER_DERIVATIVE_SELECTED, `vp ${viewport.name}`).toBe(
        "YES",
      );
      expect(capture.assertions.SUPPORT_DUPLICATE_DESKTOP_MOBILE_DOWNLOAD, `vp ${viewport.name}`).toBe(
        "NO",
      );
    }
  }

  fs.mkdirSync(EVIDENCE_DIR, { recursive: true });
  fs.writeFileSync(
    NETWORK_JSON,
    JSON.stringify(
      {
        generatedAt: new Date().toISOString(),
        captures,
      },
      null,
      2,
    ),
    "utf8",
  );
});
