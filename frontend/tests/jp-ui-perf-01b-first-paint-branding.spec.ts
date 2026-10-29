import { expect, test, type Page } from "@playwright/test";
import fs from "node:fs";
import path from "node:path";

const EVIDENCE_DIR = path.resolve(
  process.cwd(),
  "../docs/evidence/jp-ui-perf-stability-01b-branding",
);
const EXPECTED_LOGO_PATH =
  process.env.JP_01B_LOGO_PATH ??
  "/storage/agencies/1/branding/1py7vHKBiZEVF7Wr9TEcaK8eRNpVYflrRyWYiMoM.png";
const FALLBACK_LOGO = "/client-assets/jetpk/logo/logo.svg";
const CONFIG_URL_RE = /\/api\/public\/content\/config(?:\?|$)/;

type LogoMutation = {
  at: string;
  src: string | null;
  srcset: string | null;
  width: string | null;
  height: string | null;
  box: { x: number; y: number; width: number; height: number } | null;
};

function ensureEvidenceDir() {
  fs.mkdirSync(EVIDENCE_DIR, { recursive: true });
}

function headerLogoInHtml(html: string): { src: string | null; width: string | null; height: string | null } {
  const tag =
    html.match(/<img[^>]*data-testid=["']jetpakistan-header-logo["'][^>]*>/i)?.[0] ??
    html.match(/<img[^>]*data-testid=["']jetpakistan-header-logo["'][\s\S]*?>/i)?.[0] ??
    null;
  if (!tag) return { src: null, width: null, height: null };
  return {
    src: tag.match(/\ssrc=["']([^"']+)["']/i)?.[1] ?? null,
    width: tag.match(/\swidth=["']([^"']+)["']/i)?.[1] ?? null,
    height: tag.match(/\sheight=["']([^"']+)["']/i)?.[1] ?? null,
  };
}

function isAuthoritativeLogo(src: string | null): boolean {
  if (!src) return false;
  return src.includes("/storage/agencies/") && src.includes("/branding/");
}

async function installLogoObserver(page: Page) {
  await page.addInitScript(() => {
    const w = window as Window & {
      __jp01bLogoMutations?: LogoMutation[];
    };
    w.__jp01bLogoMutations = [];
    const push = (at: string, el: Element | null) => {
      if (!(el instanceof HTMLImageElement)) return;
      const rect = el.getBoundingClientRect();
      w.__jp01bLogoMutations!.push({
        at,
        src: el.getAttribute("src"),
        srcset: el.getAttribute("srcset"),
        width: el.getAttribute("width"),
        height: el.getAttribute("height"),
        box: { x: rect.x, y: rect.y, width: rect.width, height: rect.height },
      });
    };

    const observe = () => {
      const logo = document.querySelector('[data-testid="jetpakistan-header-logo"]');
      push("dom-ready", logo);
      if (!logo) return;
      const mo = new MutationObserver(() => push("mutation", logo));
      mo.observe(logo, {
        attributes: true,
        attributeFilter: ["src", "srcset", "width", "height", "style"],
      });
      (w as Window & { __jp01bLogoMo?: MutationObserver }).__jp01bLogoMo = mo;
    };

    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", observe, { once: true });
    } else {
      observe();
    }
  });
}

async function readMutations(page: Page): Promise<LogoMutation[]> {
  return page.evaluate(() => {
    const w = window as Window & { __jp01bLogoMutations?: LogoMutation[] };
    return w.__jp01bLogoMutations ?? [];
  });
}

async function logoGeometry(page: Page) {
  return page.locator('[data-testid="jetpakistan-header-logo"]').first().evaluate((el) => {
    const img = el as HTMLImageElement;
    const rect = img.getBoundingClientRect();
    return {
      src: img.currentSrc || img.getAttribute("src") || "",
      complete: img.complete,
      naturalWidth: img.naturalWidth,
      naturalHeight: img.naturalHeight,
      widthAttr: img.getAttribute("width"),
      heightAttr: img.getAttribute("height"),
      box: { width: rect.width, height: rect.height, x: rect.x, y: rect.y },
    };
  });
}

test.describe("JP-UI-PERF-01B first-paint branding", () => {
  test.beforeAll(() => {
    ensureEvidenceDir();
  });

  for (const route of ["/", "/login"] as const) {
    test(`${route} SSR HTML contains authoritative logo`, async ({ page }) => {
      const response = await page.request.get(route);
      expect(response.ok()).toBeTruthy();
      const html = await response.text();
      const logo = headerLogoInHtml(html);

      fs.writeFileSync(
        path.join(EVIDENCE_DIR, `ssr-${route.replace(/\W+/g, "_") || "home"}.html.snippet.txt`),
        [
          `route=${route}`,
          `logo_src=${logo.src}`,
          `width=${logo.width}`,
          `height=${logo.height}`,
          `has_fallback=${html.includes(FALLBACK_LOGO)}`,
          `has_expected_path=${html.includes(EXPECTED_LOGO_PATH)}`,
        ].join("\n"),
        "utf8",
      );

      expect(isAuthoritativeLogo(logo.src), `SSR logo src for ${route}: ${logo.src}`).toBeTruthy();
      expect(logo.src?.includes(FALLBACK_LOGO) ?? false).toBeFalsy();
      expect(Number(logo.height)).toBe(36);
    });
  }

  test("flights layout SSR-wires branding (no page at /flights; layout source + prod HTML)", async ({
    request,
  }) => {
    const layoutSrc = fs.readFileSync(path.resolve(process.cwd(), "app/flights/layout.tsx"), "utf8");
    expect(layoutSrc.includes("PublicConfigService.getConfig()")).toBeTruthy();
    expect(layoutSrc.includes("logo_url: config.logo_url")).toBeTruthy();
    expect(layoutSrc.includes("branding={branding}")).toBeTruthy();

    // Read-only production proof — /flights is not a local app page under mock.
    const prod = await request.get("https://jetpakistan.pk/flights", { timeout: 60_000 });
    expect(prod.ok()).toBeTruthy();
    const html = await prod.text();
    const logo = headerLogoInHtml(html);
    fs.writeFileSync(
      path.join(EVIDENCE_DIR, "ssr-_flights-prod.html.snippet.txt"),
      [
        `route=/flights`,
        `source=production-readonly`,
        `logo_src=${logo.src}`,
        `width=${logo.width}`,
        `height=${logo.height}`,
      ].join("\n"),
      "utf8",
    );
    expect(isAuthoritativeLogo(logo.src)).toBeTruthy();
    expect(Number(logo.height)).toBe(36);
  });

  test("no hydration logo swap and no browser config refetch when SSR branding present", async ({
    page,
  }) => {
    const configRequests: string[] = [];
    page.on("request", (req) => {
      if (CONFIG_URL_RE.test(req.url())) {
        configRequests.push(req.url());
      }
    });

    await installLogoObserver(page);

    await page.goto("/", { waitUntil: "domcontentloaded" });
    const beforeDecode = await logoGeometry(page);

    await page.waitForFunction(() => document.documentElement.dataset.jpHydrated === "1", {
      timeout: 30_000,
    });
    await page.waitForLoadState("networkidle").catch(() => null);

    // Allow image decode without treating it as branding swap.
    await page
      .locator('[data-testid="jetpakistan-header-logo"]')
      .first()
      .evaluate(async (el) => {
        const img = el as HTMLImageElement;
        if (!img.complete) {
          await new Promise<void>((resolve) => {
            img.addEventListener("load", () => resolve(), { once: true });
            img.addEventListener("error", () => resolve(), { once: true });
          });
        }
      });

    const after = await logoGeometry(page);
    const mutations = await readMutations(page);
    const srcChanges = mutations.filter((m, i, arr) => {
      if (i === 0) return false;
      return m.src !== arr[i - 1]?.src;
    });

    const widthDelta = Math.abs(after.box.width - beforeDecode.box.width);
    const heightDelta = Math.abs(after.box.height - beforeDecode.box.height);

    fs.writeFileSync(
      path.join(EVIDENCE_DIR, "hydration-logo-mutations.json"),
      JSON.stringify(
        {
          beforeDecode,
          after,
          mutations,
          srcChanges,
          configRequests,
          widthDelta,
          heightDelta,
        },
        null,
        2,
      ),
      "utf8",
    );

    expect(isAuthoritativeLogo(after.src)).toBeTruthy();
    expect(srcChanges.length, `logo src mutations: ${JSON.stringify(srcChanges)}`).toBe(0);
    expect(configRequests.length, `browser config fetches: ${configRequests.join(", ")}`).toBe(0);
    expect(widthDelta).toBeLessThanOrEqual(1);
    expect(heightDelta).toBeLessThanOrEqual(1);
  });

  test("login SSR branding present → zero browser config fetch", async ({ page }) => {
    const configRequests: string[] = [];
    page.on("request", (req) => {
      if (CONFIG_URL_RE.test(req.url())) configRequests.push(req.url());
    });

    await installLogoObserver(page);
    await page.goto("/login", { waitUntil: "domcontentloaded" });
    await page.waitForFunction(() => document.documentElement.dataset.jpHydrated === "1", {
      timeout: 30_000,
    });
    await page.waitForLoadState("networkidle").catch(() => null);

    const logo = await logoGeometry(page);
    const mutations = await readMutations(page);
    const srcChanges = mutations.filter((m, i, arr) => i > 0 && m.src !== arr[i - 1]?.src);

    fs.writeFileSync(
      path.join(EVIDENCE_DIR, "login-hydration.json"),
      JSON.stringify({ logo, mutations, srcChanges, configRequests }, null, 2),
      "utf8",
    );

    expect(isAuthoritativeLogo(logo.src)).toBeTruthy();
    expect(srcChanges.length).toBe(0);
    expect(configRequests.length).toBe(0);
  });

  test("header branding stable across key viewports", async ({ page }) => {
    const viewports = [
      { w: 320, h: 720 },
      { w: 360, h: 740 },
      { w: 390, h: 844 },
      { w: 430, h: 932 },
      { w: 768, h: 1024 },
      { w: 1024, h: 768 },
      { w: 1440, h: 900 },
    ];

    const rows: Array<Record<string, unknown>> = [];
    for (const vp of viewports) {
      await page.setViewportSize({ width: vp.w, height: vp.h });
      await page.goto("/", { waitUntil: "domcontentloaded" });
      await page.waitForFunction(() => document.documentElement.dataset.jpHydrated === "1").catch(
        () => null,
      );
      const logo = page.locator('[data-testid="jetpakistan-header-logo"]').first();
      const box = await logo.boundingBox();
      const account = page.locator('header a[href="/login"], header button').first();
      const accountBox = await account.boundingBox().catch(() => null);
      const header = page.locator("header").first();
      const headerBox = await header.boundingBox();

      const overlap =
        box &&
        accountBox &&
        !(
          box.x + box.width <= accountBox.x ||
          accountBox.x + accountBox.width <= box.x ||
          box.y + box.height <= accountBox.y ||
          accountBox.y + accountBox.height <= box.y
        );

      rows.push({
        viewport: vp,
        logoBox: box,
        headerHeight: headerBox?.height ?? null,
        overlapAccount: Boolean(overlap),
        clipped: Boolean(box && (box.x < 0 || box.width < 8)),
      });

      expect(box, `logo visible at ${vp.w}`).toBeTruthy();
      expect(overlap, `logo overlaps account at ${vp.w}`).toBeFalsy();
    }

    fs.writeFileSync(
      path.join(EVIDENCE_DIR, "header-viewports.json"),
      JSON.stringify(rows, null, 2),
      "utf8",
    );
  });
});
