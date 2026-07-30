import type { Page } from "@playwright/test";
import type { ThemeMode } from "./jp-ui-02-scenarios";

export type JpUi03aScenario = {
  id: string;
  family: string;
  route: string;
  theme: ThemeMode;
  viewport: { name: string; width: number; height: number };
  zoom: number;
  state: string;
  fullPage?: boolean;
  waitForTestId?: string;
  setup?: (page: Page) => Promise<void>;
  action?: (page: Page) => Promise<void>;
};

type Viewport = { name: string; width: number; height: number };

const VP = {
  d1440: { name: "1440x1200", width: 1440, height: 1200 },
  d1280: { name: "1280x900", width: 1280, height: 900 },
  d1024: { name: "1024x900", width: 1024, height: 900 },
  t768: { name: "768x1024", width: 768, height: 1024 },
  m390: { name: "390x844", width: 390, height: 844 },
  m375: { name: "375x812", width: 375, height: 812 },
  m320: { name: "320x700", width: 320, height: 700 },
} as const;

function hp(id: string, theme: ThemeMode, viewport: Viewport, state: string, extra?: Partial<JpUi03aScenario>): JpUi03aScenario {
  return {
    id,
    family: "homepage",
    route: "/",
    theme,
    viewport,
    zoom: 1,
    state,
    waitForTestId: "search-module",
    fullPage: true,
    ...extra,
  };
}

function about(id: string, theme: ThemeMode, viewport: Viewport, state: string, extra?: Partial<JpUi03aScenario>): JpUi03aScenario {
  return { id, family: "about", route: "/about-us", theme, viewport, zoom: 1, state, fullPage: true, ...extra };
}

function support(id: string, theme: ThemeMode, viewport: Viewport, state: string, extra?: Partial<JpUi03aScenario>): JpUi03aScenario {
  return { id, family: "support", route: "/support", theme, viewport, zoom: 1, state, fullPage: true, ...extra };
}

function pub(id: string, family: string, route: string, theme: ThemeMode, viewport: Viewport, state: string, extra?: Partial<JpUi03aScenario>): JpUi03aScenario {
  return { id, family, route, theme, viewport, zoom: 1, state, fullPage: true, ...extra };
}

function buildHomepageBase(): JpUi03aScenario[] {
  const rows: Array<[string, ThemeMode, Viewport]> = [
    ["hp-01", "light", VP.d1440],
    ["hp-02", "dark", VP.d1440],
    ["hp-03", "system-light", VP.d1440],
    ["hp-04", "system-dark", VP.d1440],
    ["hp-05", "light", VP.d1280],
    ["hp-06", "dark", VP.d1280],
    ["hp-07", "light", VP.d1024],
    ["hp-08", "dark", VP.d1024],
    ["hp-09", "light", VP.t768],
    ["hp-10", "dark", VP.t768],
    ["hp-11", "light", VP.m390],
    ["hp-12", "dark", VP.m390],
    ["hp-13", "system-light", VP.m390],
    ["hp-14", "system-dark", VP.m390],
    ["hp-15", "light", VP.m375],
    ["hp-16", "dark", VP.m375],
    ["hp-17", "light", VP.m320],
    ["hp-18", "dark", VP.m320],
  ];
  return rows.map(([id, theme, vp]) => hp(id, theme, vp, "base-layout"));
}

function buildHomepageZoom(): JpUi03aScenario[] {
  return [
    { ...hp("hp-19", "light", VP.d1280, "zoom-125"), zoom: 1.25 },
    { ...hp("hp-20", "dark", VP.d1280, "zoom-125"), zoom: 1.25 },
    { ...hp("hp-21", "light", VP.d1280, "zoom-150"), zoom: 1.5 },
    { ...hp("hp-22", "dark", VP.d1280, "zoom-150"), zoom: 1.5 },
  ];
}

function buildHomepageSearchStates(): JpUi03aScenario[] {
  const desktop = VP.d1440;
  const mobile = VP.m390;
  const tab = (id: string, theme: ThemeMode, tabName: string) =>
    hp(id, theme, desktop, `search-tab-${tabName.toLowerCase().replace(/\s+/g, "-")}`, {
      action: async (page) => {
        await page.getByRole("tab", { name: tabName }).click();
      },
    });

  return [
    tab("hp-23", "light", "One Way"),
    tab("hp-24", "dark", "One Way"),
    tab("hp-25", "light", "Return"),
    tab("hp-26", "dark", "Return"),
    tab("hp-27", "light", "Multi-City"),
    tab("hp-28", "dark", "Multi-City"),
  hp("hp-29", "light", desktop, "search-tab-group-ticketing", {
      action: async (page) => {
        const { mockGroupFacets } = await import("./jp-ui-03a-fixtures");
        await mockGroupFacets(page);
        await page.getByRole("tab", { name: "Group Ticketing" }).click();
      },
    }),
  hp("hp-30", "dark", desktop, "search-tab-group-ticketing", {
      action: async (page) => {
        const { mockGroupFacets } = await import("./jp-ui-03a-fixtures");
        await mockGroupFacets(page);
        await page.getByRole("tab", { name: "Group Ticketing" }).click();
      },
    }),
    hp("hp-31", "light", desktop, "autocomplete-origin-open", {
      action: async (page) => {
        const field = page.getByRole("combobox", { name: "From" });
        await field.click();
        await field.fill("Lahore");
      },
    }),
    hp("hp-32", "dark", desktop, "autocomplete-destination-open", {
      action: async (page) => {
        const field = page.getByRole("combobox", { name: "To" });
        await field.click();
        await field.fill("Dubai");
      },
    }),
    hp("hp-33", "light", desktop, "validation-error", {
      action: async (page) => {
        await page.getByRole("button", { name: "Search Flights" }).click();
      },
    }),
    hp("hp-34", "dark", desktop, "validation-error", {
      action: async (page) => {
        await page.getByRole("button", { name: "Search Flights" }).click();
      },
    }),
    hp("hp-35", "light", desktop, "traveler-panel-open", {
      action: async (page) => {
        await page.getByTestId("travelers-cabin-trigger").first().click();
      },
    }),
    hp("hp-36", "dark", desktop, "traveler-panel-open", {
      action: async (page) => {
        await page.getByTestId("travelers-cabin-trigger").first().click();
      },
    }),
    hp("hp-37", "light", desktop, "date-field-focused", {
      action: async (page) => {
        await page.getByLabel("Departure").focus();
      },
    }),
    hp("hp-38", "light", mobile, "mobile-search-active", {
      action: async (page) => {
        await page.getByRole("combobox", { name: "From" }).click();
      },
    }),
    hp("hp-39", "dark", mobile, "mobile-search-active", {
      action: async (page) => {
        await page.getByRole("combobox", { name: "From" }).click();
      },
    }),
  ];
}

function buildHomepageContentStates(): JpUi03aScenario[] {
  const desktop = VP.d1440;
  const withVariant = (id: string, state: string, variant: Parameters<typeof import("./jp-ui-03a-fixtures").mockHomepageVariant>[1]) =>
    hp(id, "light", desktop, state, {
      setup: async (page) => {
        const { mockHomepageVariant } = await import("./jp-ui-03a-fixtures");
        await mockHomepageVariant(page, variant);
      },
    });

  return [
    withVariant("hp-40", "hero-media-present", "full"),
    withVariant("hp-41", "hero-media-fallback", "hero-fallback"),
    withVariant("hp-42", "destinations-present", "full"),
    withVariant("hp-43", "destinations-empty", "no-routes"),
    withVariant("hp-44", "offers-present", "full"),
    withVariant("hp-45", "offers-empty", "no-offers"),
    withVariant("hp-46", "support-cta-present", "full"),
    hp("hp-47", "light", desktop, "homepage-api-failure", {
      setup: async (page) => {
        const { mockHomepageVariant } = await import("./jp-ui-03a-fixtures");
        await mockHomepageVariant(page, "api-failure");
      },
    }),
    hp("hp-48", "light", desktop, "airport-api-error", {
      setup: async (page) => {
        const { mockAirportApiError, setupPublicBaseline } = await import("./jp-ui-03a-fixtures");
        await setupPublicBaseline(page);
        await mockAirportApiError(page);
      },
      action: async (page) => {
        const field = page.getByRole("combobox", { name: "From" });
        await field.click();
        await field.fill("Lah");
      },
    }),
  ];
}

function buildAboutMatrix(): JpUi03aScenario[] {
  const rows: Array<[string, ThemeMode, Viewport, string, Partial<JpUi03aScenario>?]> = [
    ["ab-01", "light", VP.d1440, "base"],
    ["ab-02", "dark", VP.d1440, "base"],
    ["ab-03", "system-light", VP.d1440, "base"],
    ["ab-04", "system-dark", VP.d1440, "base"],
    ["ab-05", "light", VP.d1024, "base"],
    ["ab-06", "dark", VP.d1024, "base"],
    ["ab-07", "light", VP.m390, "base"],
    ["ab-08", "dark", VP.m390, "base"],
    ["ab-09", "light", VP.m320, "base"],
    ["ab-10", "dark", VP.m320, "base"],
    ["ab-11", "light", VP.d1280, "zoom-150", { zoom: 1.5 }],
    ["ab-12", "dark", VP.d1280, "zoom-150", { zoom: 1.5 }],
    ["ab-13", "light", VP.d1440, "cms-full", {
      setup: async (page) => {
        const { mockManagedPage, ABOUT_FULL, setupPublicBaseline } = await import("./jp-ui-03a-fixtures");
        await setupPublicBaseline(page);
        await mockManagedPage(page, "about", ABOUT_FULL);
      },
    }],
    ["ab-14", "light", VP.d1440, "cms-minimal", {
      setup: async (page) => {
        const { mockManagedPage, ABOUT_MINIMAL, setupPublicBaseline } = await import("./jp-ui-03a-fixtures");
        await setupPublicBaseline(page);
        await mockManagedPage(page, "about", ABOUT_MINIMAL);
      },
    }],
    ["ab-15", "light", VP.d1440, "hero-text-only", {
      setup: async (page) => {
        const { mockManagedPage, ABOUT_MINIMAL, setupPublicBaseline } = await import("./jp-ui-03a-fixtures");
        await setupPublicBaseline(page);
        await mockManagedPage(page, "about", ABOUT_MINIMAL);
      },
    }],
    ["ab-16", "dark", VP.d1440, "cms-full-dark", {
      setup: async (page) => {
        const { mockManagedPage, ABOUT_FULL, setupPublicBaseline } = await import("./jp-ui-03a-fixtures");
        await setupPublicBaseline(page);
        await mockManagedPage(page, "about", ABOUT_FULL);
      },
    }],
  ];
  return rows.map(([id, theme, vp, state, extra]) => about(id, theme, vp, state, extra));
}

function buildSupportMatrix(): JpUi03aScenario[] {
  const d = VP.d1440;
  const m = VP.m390;
  const baseRows: Array<[string, ThemeMode, Viewport, string]> = [
    ["sp-01", "light", d, "base"],
    ["sp-02", "dark", d, "base"],
    ["sp-03", "system-light", d, "base"],
    ["sp-04", "system-dark", d, "base"],
    ["sp-05", "light", VP.d1024, "base"],
    ["sp-06", "dark", VP.d1024, "base"],
    ["sp-07", "light", m, "base"],
    ["sp-08", "dark", m, "base"],
    ["sp-09", "light", VP.m320, "base"],
    ["sp-10", "dark", VP.m320, "base"],
    ["sp-11", "light", VP.d1280, "zoom-150"],
    ["sp-12", "dark", VP.d1280, "zoom-150"],
  ];
  const states: JpUi03aScenario[] = baseRows.map(([id, theme, vp, state]) =>
    state.includes("zoom")
      ? { ...support(id, theme, vp, state), zoom: 1.5 }
      : support(id, theme, vp, state),
  );

  states.push(
    support("sp-13", "light", d, "faq-closed"),
    support("sp-14", "light", d, "faq-expanded", {
      action: async (page) => {
        await page.goto("/faq", { waitUntil: "load" });
        await page.getByRole("button", { name: /How do I book a flight/i }).click();
      },
      route: "/faq",
      family: "support",
    }),
    support("sp-15", "light", d, "support-search-initial"),
    support("sp-16", "light", d, "support-search-results", {
      action: async (page) => {
        await page.getByLabel(/Search support topics/i).fill("booking");
      },
    }),
    support("sp-17", "light", d, "support-search-no-results", {
      action: async (page) => {
        await page.getByLabel(/Search support topics/i).fill("zzznomatch");
      },
    }),
    pub("sp-18", "support", "/contact", "light", d, "contact-form-initial"),
    pub("sp-19", "support", "/contact", "light", d, "contact-validation", {
      action: async (page) => {
        await page.getByTestId("contact-form").getByRole("button", { name: "Send message" }).click();
      },
    }),
    support("sp-20", "light", d, "turnstile-required", {
      setup: async (page) => {
        const { setupPublicBaseline, mockTurnstileEnabled } = await import("./jp-ui-03a-fixtures");
        await setupPublicBaseline(page);
        await mockTurnstileEnabled(page);
      },
    }),
    support("sp-21", "light", d, "turnstile-provider-error", {
      setup: async (page) => {
        const { setupPublicBaseline, mockTurnstileProviderError } = await import("./jp-ui-03a-fixtures");
        await setupPublicBaseline(page);
        await mockTurnstileProviderError(page);
      },
    }),
    pub("sp-22", "support", "/contact", "light", d, "laravel-rejection", {
      setup: async (page) => {
        const { setupPublicBaseline } = await import("./jp-ui-03a-fixtures");
        await setupPublicBaseline(page);
        await page.route("**/laravel/support", async (route) => {
          await route.fulfill({ status: 422, contentType: "application/json", body: JSON.stringify({ message: "Invalid", errors: { email: ["Invalid email"] } }) });
        });
      },
      action: async (page) => {
        const form = page.getByTestId("contact-form");
        await form.getByLabel("Your name").fill("Audit User");
        await form.getByLabel("Email").fill("bad");
        await form.getByLabel("Message").fill("Test");
        await form.getByRole("button", { name: "Send message" }).click();
      },
    }),
    pub("sp-23", "support", "/contact", "light", d, "rate-limit", {
      setup: async (page) => {
        const { setupPublicBaseline, mockSupportRateLimit } = await import("./jp-ui-03a-fixtures");
        await setupPublicBaseline(page);
        await mockSupportRateLimit(page);
      },
    }),
    pub("sp-24", "support", "/contact", "light", d, "success", {
      setup: async (page) => {
        const { setupPublicBaseline, mockSupportSuccess } = await import("./jp-ui-03a-fixtures");
        await setupPublicBaseline(page);
        await mockSupportSuccess(page);
      },
      action: async (page) => {
        const form = page.getByTestId("contact-form");
        await form.getByLabel("Your name").fill("Audit User");
        await form.getByLabel("Email").fill("audit@example.com");
        await form.getByLabel("Message").fill("Accepted fixture response.");
        await form.getByRole("button", { name: "Send message" }).click();
      },
    }),
    support("sp-25", "light", d, "categories-present"),
    support("sp-26", "light", d, "faq-teaser-present"),
    support("sp-27", "light", d, "contact-methods-present"),
    support("sp-28", "dark", d, "contact-methods-dark"),
  );
  return states;
}

function buildPublicCmsLegal(): JpUi03aScenario[] {
  const d = VP.d1440;
  const m = VP.m390;
  return [
    pub("cms-01", "faq", "/faq", "light", d, "faq-light"),
    pub("cms-02", "faq", "/faq", "dark", d, "faq-dark"),
    pub("cms-03", "faq", "/faq", "light", m, "faq-mobile-light"),
    pub("cms-04", "faq", "/faq", "dark", m, "faq-mobile-dark"),
    pub("cms-05", "faq", "/faq", "light", d, "faq-expanded", {
      action: async (page) => {
        await page.getByRole("button", { name: /How do I book a flight/i }).click();
      },
    }),
    pub("cms-06", "faq", "/faq", "light", d, "faq-empty", {
      setup: async (page) => {
        const { setupPublicBaseline, mockManagedPage } = await import("./jp-ui-03a-fixtures");
        await setupPublicBaseline(page);
        await mockManagedPage(page, "faq", { page_key: "faq", source: "empty", content: {}, seo: { title: "FAQ" } });
      },
    }),
    pub("cms-07", "cms", "/pages/travel-info", "light", d, "cms-published-light", {
      setup: async (page) => {
        const { setupPublicBaseline, mockCmsPage, CMS_RICH_PAGE } = await import("./jp-ui-03a-fixtures");
        await setupPublicBaseline(page);
        await mockCmsPage(page, "travel-info", CMS_RICH_PAGE);
      },
    }),
    pub("cms-08", "cms", "/pages/travel-info", "dark", d, "cms-published-dark", {
      setup: async (page) => {
        const { setupPublicBaseline, mockCmsPage, CMS_RICH_PAGE } = await import("./jp-ui-03a-fixtures");
        await setupPublicBaseline(page);
        await mockCmsPage(page, "travel-info", CMS_RICH_PAGE);
      },
    }),
    pub("cms-09", "cms", "/pages/travel-info", "light", m, "cms-mobile", {
      setup: async (page) => {
        const { setupPublicBaseline, mockCmsPage, CMS_RICH_PAGE } = await import("./jp-ui-03a-fixtures");
        await setupPublicBaseline(page);
        await mockCmsPage(page, "travel-info", CMS_RICH_PAGE);
      },
    }),
    pub("cms-10", "cms", "/pages/travel-info", "light", d, "cms-image-rich", {
      setup: async (page) => {
        const { setupPublicBaseline, mockCmsPage, CMS_RICH_PAGE } = await import("./jp-ui-03a-fixtures");
        await setupPublicBaseline(page);
        await mockCmsPage(page, "travel-info", CMS_RICH_PAGE);
      },
    }),
    pub("cms-11", "cms", "/pages/travel-info", "light", d, "cms-image-missing", {
      setup: async (page) => {
        const { setupPublicBaseline, mockCmsPage, CMS_IMAGE_MISSING } = await import("./jp-ui-03a-fixtures");
        await setupPublicBaseline(page);
        await mockCmsPage(page, "travel-info", CMS_IMAGE_MISSING);
      },
    }),
    pub("cms-12", "cms", "/pages/travel-info", "light", d, "cms-responsive-table", {
      setup: async (page) => {
        const { setupPublicBaseline, mockCmsPage, CMS_RICH_PAGE } = await import("./jp-ui-03a-fixtures");
        await setupPublicBaseline(page);
        await mockCmsPage(page, "travel-info", CMS_RICH_PAGE);
      },
    }),
    pub("cms-13", "cms", "/pages/travel-info", "light", d, "cms-internal-link", {
      setup: async (page) => {
        const { setupPublicBaseline, mockCmsPage, CMS_RICH_PAGE } = await import("./jp-ui-03a-fixtures");
        await setupPublicBaseline(page);
        await mockCmsPage(page, "travel-info", CMS_RICH_PAGE);
      },
    }),
    pub("cms-14", "cms", "/pages/travel-info", "light", d, "cms-external-link", {
      setup: async (page) => {
        const { setupPublicBaseline, mockCmsPage, CMS_RICH_PAGE } = await import("./jp-ui-03a-fixtures");
        await setupPublicBaseline(page);
        await mockCmsPage(page, "travel-info", CMS_RICH_PAGE);
      },
    }),
    pub("cms-15", "cms", "/pages/missing-page", "light", d, "cms-not-found", {
      setup: async (page) => {
        const { setupPublicBaseline } = await import("./jp-ui-03a-fixtures");
        await setupPublicBaseline(page);
        await page.route("**/laravel/api/public/content/cms/missing-page", async (route) => {
          await route.fulfill({ status: 404, contentType: "application/json", body: "{}" });
        });
      },
    }),
    pub("cms-16", "cms", "/pages/travel-info", "light", d, "cms-api-failure", {
      setup: async (page) => {
        const { setupPublicBaseline } = await import("./jp-ui-03a-fixtures");
        await setupPublicBaseline(page);
        await page.route("**/laravel/api/public/content/cms/travel-info", async (route) => {
          await route.fulfill({ status: 503, contentType: "application/json", body: JSON.stringify({ message: "Unavailable" }) });
        });
      },
    }),
    pub("cms-17", "legal", "/terms", "light", d, "terms-light"),
    pub("cms-18", "legal", "/terms", "dark", d, "terms-dark"),
    pub("cms-19", "legal", "/privacy", "light", d, "privacy-light"),
    pub("cms-20", "legal", "/privacy", "dark", d, "privacy-dark"),
    pub("cms-21", "legal", "/terms", "light", m, "legal-mobile"),
    pub("cms-22", "legal", "/terms", "light", VP.d1280, "legal-zoom-150", { zoom: 1.5 }),
    pub("cms-23", "legal", "/legal/unpublished", "light", d, "legal-unpublished", {
      setup: async (page) => {
        const { setupPublicBaseline } = await import("./jp-ui-03a-fixtures");
        await setupPublicBaseline(page);
        await page.route("**/laravel/api/public/content/pages/*", async (route) => {
          await route.fulfill({ status: 404, contentType: "application/json", body: "{}" });
        });
      },
    }),
    pub("err-24", "error", "/this-route-does-not-exist-jp-ui-03a", "light", d, "404-light"),
    pub("err-25", "error", "/this-route-does-not-exist-jp-ui-03a", "dark", d, "404-dark"),
    pub("err-26", "error", "/", "light", d, "public-shell-light"),
    pub("err-27", "error", "/", "dark", d, "public-shell-dark"),
  ];
}

export function buildJpUi03aScenarios(): JpUi03aScenario[] {
  return [
    ...buildHomepageBase(),
    ...buildHomepageZoom(),
    ...buildHomepageSearchStates(),
    ...buildHomepageContentStates(),
    ...buildAboutMatrix(),
    ...buildSupportMatrix(),
    ...buildPublicCmsLegal(),
  ];
}

export const JP_UI_03A_SCENARIOS = buildJpUi03aScenarios();

export const EXPECTED_SCENARIO_COUNT = JP_UI_03A_SCENARIOS.length;

const ids = JP_UI_03A_SCENARIOS.map((s) => s.id);
if (new Set(ids).size !== ids.length) {
  throw new Error(`Duplicate JP-UI-03A scenario ids detected: ${ids.length} total, ${new Set(ids).size} unique`);
}
