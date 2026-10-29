/**
 * Minimal Laravel-shaped public APIs for JP-UI-PERF-01B branding SSR proof.
 * Serves authoritative org branding on /api/public/content/config.
 * Optional fail mode: JP_01B_CONFIG_FAIL=1 returns 503 (null-cache recovery harness).
 */
import http from "node:http";

const port = Number(process.env.JP_01B_MOCK_API_PORT ?? "18081");

/** Sanitized production-shaped branding (path only — no secrets). */
const EXPECTED_LOGO_PATH =
  process.env.JP_01B_LOGO_PATH ??
  "/storage/agencies/1/branding/1py7vHKBiZEVF7Wr9TEcaK8eRNpVYflrRyWYiMoM.png";

const publicConfig = {
  brand_name: "JetPakistan",
  domain: "jetpakistan.pk",
  app_url: "https://jetpakistan.pk",
  logo_url: EXPECTED_LOGO_PATH,
  favicon_url: "/client-assets/jetpk/logo/favicon.ico",
  header_logo_height: 36,
  contact: {
    phone: null,
    email: null,
    whatsapp: null,
    address: null,
  },
  legal_paths: { terms: "/terms", privacy: "/privacy" },
  support_path: "/support",
  contact_path: "/contact",
  booking_lookup_path: "/booking-lookup",
  groups_path: "/groups/search",
  social_links: [],
  default_seo: {
    title: "JetPakistan",
    description: "Fly Smart, Fly Easy",
    robots: "index,follow",
  },
  source: "laravel",
  ai_assistant_enabled: false,
  commerce_gates: {
    guest_booking_enabled: true,
    card_payment_enabled: true,
  },
};

const emptyHomepage = {
  hero: null,
  destinations: [],
  popular_routes: [],
  featured_deals: [],
  support: null,
};

let configHits = 0;
let configFailsForced = 0;

function json(res, status, body) {
  const payload = JSON.stringify(body);
  res.writeHead(status, {
    "Content-Type": "application/json",
    "Cache-Control": "no-store",
    "Content-Length": Buffer.byteLength(payload),
  });
  res.end(payload);
}

const server = http.createServer((req, res) => {
  const url = new URL(req.url ?? "/", `http://127.0.0.1:${port}`);
  const pathname = url.pathname.replace(/\/$/, "") || "/";

  if (pathname === "/api/public/content/config") {
    configHits += 1;
    if (process.env.JP_01B_CONFIG_FAIL === "1") {
      configFailsForced += 1;
      json(res, 503, { message: "unavailable" });
      return;
    }
    // After N failures, allow recovery when JP_01B_FAIL_THEN_OK is set.
    const failThenOk = Number(process.env.JP_01B_FAIL_THEN_OK ?? "0");
    if (failThenOk > 0 && configHits <= failThenOk) {
      json(res, 503, { message: "transient" });
      return;
    }
    json(res, 200, publicConfig);
    return;
  }

  if (pathname === "/api/public/content/homepage") {
    json(res, 200, emptyHomepage);
    return;
  }

  if (pathname.startsWith("/api/public/content/pages")) {
    json(res, 404, { message: "not found" });
    return;
  }

  if (
    pathname === "/api/public/auth/session" ||
    pathname === "/api/auth/session" ||
    pathname === "/laravel/api/auth/session" ||
    pathname === "/laravel/api/public/auth/session"
  ) {
    json(res, 200, {
      status: "anonymous",
      authenticated: false,
      user: null,
    });
    return;
  }

  if (pathname === "/__jp01b/metrics") {
    json(res, 200, { configHits, configFailsForced, logo_path: EXPECTED_LOGO_PATH });
    return;
  }

  json(res, 404, { message: `no mock for ${pathname}` });
});

server.listen(port, "127.0.0.1", () => {
  console.log(
    JSON.stringify({
      scope: "jp-01b-branding-mock",
      port,
      logo_path: EXPECTED_LOGO_PATH,
    }),
  );
});
