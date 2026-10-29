/**
 * Controlled Laravel-shaped homepage API for PR #139 responsive-media browser proof.
 * Serves /api/public/content/homepage with derivative srcset/preloads pointing at
 * /_evidence/pr139-responsive/* assets served by Next.
 */
import http from "node:http";

const port = Number(process.env.PR139_MOCK_API_PORT ?? "18080");
const B = "/_evidence/pr139-responsive";

const cardResponsive = {
  fallback_url: `${B}/card-640.jpg`,
  width: 640,
  height: 400,
  sizes: "(max-width: 768px) 100vw, 320px",
  has_responsive_variants: true,
  fingerprint: "pr139-card",
  sources: [
    {
      type: "image/avif",
      srcset: `${B}/card-320.avif 320w, ${B}/card-640.avif 640w`,
    },
    {
      type: "image/webp",
      srcset: `${B}/card-320.webp 320w, ${B}/card-640.webp 640w`,
    },
    {
      type: "image/jpeg",
      srcset: `${B}/card-640.jpg 640w`,
    },
  ],
};

const heroResponsive = {
  fallback_url: `${B}/hero-desktop.jpg`,
  width: 1200,
  height: 600,
  sizes: "100vw",
  has_responsive_variants: true,
  fingerprint: "pr139-hero",
  preloads: [
    {
      href: `${B}/hero-desktop.avif`,
      type: "image/avif",
      media: "(min-width: 1025px)",
    },
    {
      href: `${B}/hero-mobile.avif`,
      type: "image/avif",
      media: "(max-width: 768px)",
    },
  ],
  sources: [
    {
      type: "image/avif",
      media: "(min-width: 1025px)",
      srcset: `${B}/hero-desktop.avif`,
    },
    {
      type: "image/webp",
      media: "(min-width: 1025px)",
      srcset: `${B}/hero-desktop.webp`,
    },
    {
      type: "image/avif",
      media: "(min-width: 769px) and (max-width: 1024px)",
      srcset: `${B}/hero-tablet.avif`,
    },
    {
      type: "image/webp",
      media: "(min-width: 769px) and (max-width: 1024px)",
      srcset: `${B}/hero-tablet.webp`,
    },
    {
      type: "image/avif",
      media: "(max-width: 768px)",
      srcset: `${B}/hero-mobile.avif`,
    },
    {
      type: "image/webp",
      media: "(max-width: 768px)",
      srcset: `${B}/hero-mobile.webp`,
    },
  ],
};

const supportResponsive = {
  fallback_url: `${B}/support-desktop.jpg`,
  width: 960,
  height: 540,
  sizes: "(max-width: 1024px) 100vw, 16rem",
  has_responsive_variants: true,
  fingerprint: "pr139-support",
  sources: [
    {
      type: "image/avif",
      srcset: `${B}/support-desktop.avif 960w`,
    },
    {
      type: "image/webp",
      srcset: `${B}/support-desktop.webp 960w`,
    },
    {
      type: "image/jpeg",
      srcset: `${B}/support-desktop.jpg 960w`,
    },
  ],
};

const payload = {
  source: "cms",
  hero: {
    eyebrow: "PR139 evidence",
    headline: "Responsive media proof",
    headline_highlight: "JetPakistan",
    subtitle: "Controlled fixture for browser derivative selection.",
    search_visible: false,
    focal_point: "center",
    overlay_strength: "medium",
    image: {
      url: `${B}/hero-original.jpg`,
      alt: "PR139 hero original",
    },
    responsive: heroResponsive,
  },
  trust_chips: [{ label: "Evidence" }],
  routes: {
    enabled: true,
    title: "Trending routes",
    items: [
      {
        id: "route-1",
        from: "KHI",
        to: "DXB",
        price_label: "from PKR 1",
        search_url: "/flights/results",
        image: `${B}/card-original.jpg`,
        image_alt: "Route card",
        responsive: cardResponsive,
      },
    ],
  },
  destinations: {
    enabled: true,
    title: "Destinations",
    items: [
      {
        id: "dest-1",
        code: "IST",
        title: "Istanbul",
        country: "Turkey",
        price_label: "from PKR 1",
        href: "/flights/results",
        image: `${B}/card-original.jpg`,
        image_alt: "Destination card",
        responsive: cardResponsive,
      },
    ],
  },
  featured_deals: {
    enabled: true,
    title: "Featured Offers",
    items: [
      {
        id: "deal-1",
        airline: "Evidence Air",
        from: "LHE",
        to: "JED",
        price_label: "from PKR 1",
        image: `${B}/card-original.jpg`,
        image_alt: "Featured deal",
        responsive: cardResponsive,
      },
    ],
  },
  why_book: {
    enabled: true,
    title: "Why JetPakistan",
    cards: [
      {
        id: "why-1",
        title: "Evidence",
        text: "Controlled proof",
        icon: "shield",
      },
    ],
  },
  support_cta: {
    enabled: true,
    title: "Need help?",
    subtitle: "Support evidence banner",
    call_enabled: false,
    chat_enabled: true,
    chat_label: "Contact Support",
    chat_href: "/support",
    image: `${B}/support-desktop.jpg`,
    responsive: supportResponsive,
    responsive_mobile: {
      fallback_url: `${B}/support-mobile.webp`,
      width: 640,
      height: 800,
      sizes: "100vw",
      has_responsive_variants: true,
      sources: [
        {
          type: "image/avif",
          srcset: `${B}/support-mobile.avif 640w`,
        },
        {
          type: "image/webp",
          srcset: `${B}/support-mobile.webp 640w`,
        },
      ],
    },
  },
  feature_board: { enabled: false, items: [] },
};

const sessionPayload = {
  authenticated: false,
  user: null,
  role: "guest",
};

const configPayload = {
  source: "cms",
  default_seo: {
    title: "JetPakistan | PR139 evidence",
    description: "Responsive media evidence fixture",
    robots: "noindex,nofollow",
  },
  favicon_url: null,
};

const server = http.createServer((req, res) => {
  const url = new URL(req.url ?? "/", `http://127.0.0.1:${port}`);
  const pathName = url.pathname.replace(/\/index\.php/g, "");

  const json = (body, status = 200) => {
    res.writeHead(status, {
      "content-type": "application/json; charset=utf-8",
      "cache-control": "no-store",
    });
    res.end(JSON.stringify(body));
  };

  if (pathName.endsWith("/api/public/content/homepage")) {
    json(payload);
    return;
  }
  if (pathName.endsWith("/api/public/content/config")) {
    json(configPayload);
    return;
  }
  if (pathName.endsWith("/api/public/auth/session")) {
    json(sessionPayload);
    return;
  }

  json({ error: "not_found", path: pathName }, 404);
});

server.listen(port, "127.0.0.1", () => {
  console.log(`[pr139-mock-api] listening on http://127.0.0.1:${port}`);
});
