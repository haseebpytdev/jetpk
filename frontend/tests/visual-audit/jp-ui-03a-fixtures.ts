import type { Page } from "@playwright/test";
import { mockCsrf, mockTurnstileDisabled } from "./jp-ui-01-fixtures";

const HOMEPAGE_CMS = {
  source: "cms",
  hero: {
    eyebrow: "Audit eyebrow",
    headline: "Explore the world with",
    headline_highlight: "JetPakistan",
    subtitle: "Audit homepage subtitle for visual matrix.",
    search_visible: true,
    image: { url: "/images/home/hero-fallback.svg", alt: "JetPakistan flights" },
  },
  trust_chips: [{ label: "Secure Booking" }, { label: "Trusted Support" }],
  routes: {
    enabled: true,
    title: "Destinations on the Rise",
    items: [
      { id: "r1", from: "LHE", to: "DXB", price_label: "PKR 48,950", search_url: "/flights/results" },
      { id: "r2", from: "KHI", to: "JED", price_label: "PKR 68,900", search_url: "/flights/results" },
    ],
  },
  destinations: { enabled: false, items: [] },
  featured_deals: {
    enabled: true,
    title: "Featured Offers",
    items: [
      {
        id: "d1",
        airline: "Audit Air",
        from: "LHE",
        to: "DXB",
        depart: "08:30",
        arrive: "11:45",
        duration: "3h 15m",
        stops: 0,
        price: 134047,
        price_label: "PKR 134,047",
      },
    ],
  },
  why_book: {
    enabled: true,
    title: "Why JetPakistan",
    cards: [{ id: "w1", title: "Best Prices", text: "Audit value proposition.", icon: "fare" }],
  },
  support_cta: {
    enabled: true,
    title: "Need help planning your trip?",
    subtitle: "Our support team is ready to assist.",
    chat_enabled: true,
    chat_label: "Contact Support",
    chat_href: "/support",
    call_enabled: false,
  },
  feature_board: { enabled: false, items: [] },
};

const HOMEPAGE_EMPTY_ROUTES = {
  ...HOMEPAGE_CMS,
  routes: { enabled: false, items: [] },
};

const HOMEPAGE_EMPTY_OFFERS = {
  ...HOMEPAGE_CMS,
  featured_deals: { enabled: false, items: [] },
};

const HOMEPAGE_HERO_FALLBACK = {
  ...HOMEPAGE_CMS,
  hero: { ...HOMEPAGE_CMS.hero, image: null },
};

const AIRPORT_SEARCH_FIXTURE = [
  { iata: "LHE", name: "Allama Iqbal International Airport", city: "Lahore", country: "Pakistan" },
  { iata: "KHI", name: "Jinnah International Airport", city: "Karachi", country: "Pakistan" },
  { iata: "ISB", name: "Islamabad International Airport", city: "Islamabad", country: "Pakistan" },
  { iata: "DXB", name: "Dubai International Airport", city: "Dubai", country: "UAE" },
  { iata: "JED", name: "King Abdulaziz International Airport", city: "Jeddah", country: "Saudi Arabia" },
];

export async function mockAirportSearch(page: Page): Promise<void> {
  await page.route("**/laravel/airports/search**", async (route) => {
    const url = new URL(route.request().url());
    const query = (url.searchParams.get("q") ?? "").trim().toLowerCase();
    const matches = query
      ? AIRPORT_SEARCH_FIXTURE.filter(
          (airport) =>
            airport.iata.toLowerCase().includes(query) ||
            airport.city.toLowerCase().includes(query) ||
            airport.name.toLowerCase().includes(query),
        )
      : AIRPORT_SEARCH_FIXTURE;
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify(matches.slice(0, 8)),
    });
  });
}

export async function setupPublicBaseline(page: Page): Promise<void> {
  await mockCsrf(page);
  await mockTurnstileDisabled(page);
  await mockAirportSearch(page);
  await page.route("**/laravel/api/public/content/homepage", async (route) => {
    await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(HOMEPAGE_CMS) });
  });
}

export async function mockHomepageVariant(
  page: Page,
  variant: "full" | "no-routes" | "no-offers" | "hero-fallback" | "api-failure",
): Promise<void> {
  await mockCsrf(page);
  await mockTurnstileDisabled(page);
  await mockAirportSearch(page);
  if (variant === "api-failure") {
    await page.route("**/laravel/api/public/content/homepage", async (route) => {
      await route.fulfill({ status: 503, contentType: "application/json", body: JSON.stringify({ message: "Unavailable" }) });
    });
    return;
  }
  const body =
    variant === "no-routes"
      ? HOMEPAGE_EMPTY_ROUTES
      : variant === "no-offers"
        ? HOMEPAGE_EMPTY_OFFERS
        : variant === "hero-fallback"
          ? HOMEPAGE_HERO_FALLBACK
          : HOMEPAGE_CMS;
  await page.route("**/laravel/api/public/content/homepage", async (route) => {
    await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(body) });
  });
}

export async function mockAirportApiError(page: Page): Promise<void> {
  await page.route("**/laravel/airports/**", async (route) => {
    await route.fulfill({ status: 503, contentType: "application/json", body: JSON.stringify({ message: "Airport service unavailable" }) });
  });
}

export async function mockGroupFacets(page: Page): Promise<void> {
  await page.route("**/laravel/groups/search/facets**", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        sectors: [{ value: "JED", label: "KSA — Jeddah" }],
        categories: [
          { value: "ksa", label: "KSA" },
          { value: "uae", label: "UAE" },
        ],
        date_bounds: { minimum: "2026-01-01", maximum: "2027-12-31" },
      }),
    });
  });
}

export async function mockTurnstileEnabled(page: Page): Promise<void> {
  await page.route("**/laravel/api/public/content/turnstile-config", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ enabled: true, site_key: "audit-turnstile-site-key", response_field: "cf-turnstile-response" }),
    });
  });
}

export async function mockTurnstileProviderError(page: Page): Promise<void> {
  await page.route("**/laravel/api/public/content/turnstile-config", async (route) => {
    await route.fulfill({ status: 503, contentType: "application/json", body: JSON.stringify({ message: "Turnstile unavailable" }) });
  });
}

export async function mockSupportRateLimit(page: Page): Promise<void> {
  await page.route("**/laravel/support", async (route) => {
    if (route.request().method() === "POST") {
      await route.fulfill({ status: 429, contentType: "application/json", body: JSON.stringify({ message: "Too many requests" }) });
      return;
    }
    await route.continue();
  });
}

export async function mockSupportSuccess(page: Page): Promise<void> {
  await page.route("**/laravel/support", async (route) => {
    if (route.request().method() === "POST") {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({ ok: true, ticket_reference: "SAUDIT01" }),
      });
      return;
    }
    await route.continue();
  });
}

export async function mockCmsPage(page: Page, slug: string, body: Record<string, unknown>, status = 200): Promise<void> {
  await page.route(`**/laravel/api/public/content/cms/${slug}`, async (route) => {
    await route.fulfill({ status, contentType: "application/json", body: JSON.stringify(body) });
  });
}

export async function mockManagedPage(page: Page, key: string, body: Record<string, unknown>): Promise<void> {
  await page.route(`**/laravel/api/public/content/pages/${key}`, async (route) => {
    await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(body) });
  });
}

export const CMS_RICH_PAGE = {
  slug: "travel-info",
  title: "Travel Information",
  subtitle: "Audit CMS page",
  body_html:
    "<p>Audit paragraph.</p><table><thead><tr><th>Item</th><th>Detail</th></tr></thead><tbody><tr><td>Baggage</td><td>30kg</td></tr></tbody></table><p><a href='/support'>Support</a> and <a href='https://example.com' rel='noopener'>external</a></p>",
  seo: { title: "Travel Information", description: "Audit", canonical: "/pages/travel-info", robots: "index,follow" },
  source: "cms",
};

export const CMS_IMAGE_MISSING = {
  ...CMS_RICH_PAGE,
  body_html: "<p>Page without hero imagery.</p>",
};

export const ABOUT_FULL = {
  page_key: "about",
  source: "cms",
  content: {
    hero: { kicker: "About", title: "Cheap flights and secure booking", description: "Audit about description." },
    content_grid: {
      items: [{ id: "story", title: "Our story", body: "Audit story paragraph.", enabled: "1" }],
    },
    feature_cards: {
      items: [{ id: "f1", title: "Trusted service", body: "Audit feature.", enabled: "1" }],
    },
    cta: { primary_label: "Search flights", primary_url: "/", secondary_label: "Contact", secondary_url: "/contact" },
  },
  seo: { title: "About", description: "About JetPakistan" },
};

export const ABOUT_MINIMAL = {
  page_key: "about",
  source: "cms",
  content: {
    hero: { title: "About JetPakistan", description: "Minimal audit about page." },
  },
  seo: { title: "About", description: "About JetPakistan" },
};
