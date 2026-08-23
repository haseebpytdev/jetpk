import { BENEFIT_FIXTURES } from "@/features/home/fixtures/benefits";
import { DESTINATION_FIXTURES } from "@/features/home/fixtures/destinations";
import { INSPIRATION_FIXTURES, VALUE_PROPOSITION_FIXTURES } from "@/features/home/fixtures/inspiration";
import { FEATURED_OFFER_FIXTURES } from "@/features/home/fixtures/offers";
import { laravelApiPath } from "@/services/flight-search";
import { allowContentFixtures, resolveContentSource } from "@/features/public-content/utils/content-policy";
import { fetchWithTimeout } from "@/features/public-content/utils/laravel-api";
import { approvedHeroMedia } from "@/lib/homepage-media";
import type {
  HomepageContent,
  HomepageDestinationCard,
  HomepageFeaturedDeal,
  HomepageHeroContent,
  HomepageRouteCard,
  HomepageSupportCta,
  HomepageTrustChip,
  HomepageWhyCard,
} from "../types/homepage";

const HERO_FALLBACK_IMAGE = approvedHeroMedia.url;
const HERO_FALLBACK_ALT = approvedHeroMedia.alt;

type RemoteHomepage = {
  source: "cms" | "empty";
  hero?: Record<string, unknown>;
  trust_chips?: Array<{ label?: string }>;
  routes?: RemoteSection & { items?: Array<Record<string, unknown>> };
  destinations?: RemoteSection & { items?: Array<Record<string, unknown>> };
  featured_deals?: RemoteSection & { items?: Array<Record<string, unknown>> };
  why_book?: RemoteSection & { cards?: Array<Record<string, unknown>> };
  support_cta?: Record<string, unknown>;
  feature_board?: { enabled?: boolean; items?: Array<Record<string, unknown>> };
};

type RemoteSection = {
  enabled?: boolean;
  eyebrow?: string;
  title?: string;
  subtitle?: string;
  cta_text?: string;
  cta_url?: string;
};

function emptyHero(): HomepageHeroContent {
  return {
    eyebrow: "",
    headline: "",
    headlineHighlight: "",
    subtitle: "",
    searchVisible: true,
    image: null,
    imageMobile: null,
  };
}

function mapHero(remote?: Record<string, unknown>): HomepageHeroContent {
  const image = remote?.image as { url?: string; alt?: string } | null | undefined;
  const imageMobile = remote?.image_mobile as { url?: string; alt?: string } | null | undefined;
  return {
    eyebrow: String(remote?.eyebrow ?? ""),
    headline: String(remote?.headline ?? ""),
    headlineHighlight: String(remote?.headline_highlight ?? ""),
    subtitle: String(remote?.subtitle ?? ""),
    searchVisible: remote?.search_visible !== false,
    focalPoint: String(remote?.focal_point ?? "center"),
    overlayStrength: String(remote?.overlay_strength ?? "medium"),
    image: image?.url
      ? { url: image.url, alt: String(image.alt ?? "JetPakistan flights") }
      : null,
    imageMobile: imageMobile?.url
      ? { url: imageMobile.url, alt: String(imageMobile.alt ?? "JetPakistan flights") }
      : null,
  };
}

function mapSectionHeader(section?: RemoteSection) {
  return {
    enabled: section?.enabled === true,
    eyebrow: String(section?.eyebrow ?? ""),
    title: String(section?.title ?? ""),
    subtitle: String(section?.subtitle ?? ""),
    ctaText: String(section?.cta_text ?? ""),
    ctaUrl: String(section?.cta_url ?? ""),
  };
}

function mapRoutes(items: Array<Record<string, unknown>> = []): HomepageRouteCard[] {
  return items.map((item, index) => ({
    id: String(item.id ?? `route-${index}`),
    from: String(item.from ?? ""),
    to: String(item.to ?? ""),
    priceLabel: String(item.price_label ?? item.price ?? ""),
    searchUrl: String(item.search_url ?? ""),
    badge: item.badge ? String(item.badge) : undefined,
    image: item.image ? String(item.image) : null,
    imageAlt: item.image_alt ? String(item.image_alt) : undefined,
  }));
}

function mapDestinations(items: Array<Record<string, unknown>> = []): HomepageDestinationCard[] {
  return items.map((item, index) => ({
    id: String(item.id ?? item.code ?? `dest-${index}`),
    code: String(item.code ?? ""),
    title: String(item.title ?? ""),
    country: item.country ? String(item.country) : undefined,
    text: item.text ? String(item.text) : undefined,
    image: item.image ? String(item.image) : null,
    priceLabel: String(item.price_label ?? ""),
    href: item.href ? String(item.href) : item.link ? String(item.link) : null,
  }));
}

function mapFeaturedDeals(items: Array<Record<string, unknown>> = []): HomepageFeaturedDeal[] {
  return items.map((item, index) => ({
    id: String(item.id ?? `deal-${index}`),
    airline: String(item.airline ?? ""),
    from: String(item.from ?? ""),
    to: String(item.to ?? ""),
    depart: String(item.depart ?? ""),
    arrive: String(item.arrive ?? ""),
    duration: String(item.duration ?? ""),
    stops: Number(item.stops ?? 0),
    priceLabel: String(item.price_label ?? ""),
    image: item.image ? String(item.image) : null,
    imageAlt: item.image_alt ? String(item.image_alt) : undefined,
  }));
}

function mapWhyCards(cards: Array<Record<string, unknown>> = []): HomepageWhyCard[] {
  return cards.map((card, index) => ({
    id: String(card.id ?? `why-${index}`),
    num: String(card.num ?? ""),
    title: String(card.title ?? ""),
    text: String(card.text ?? ""),
    icon: String(card.icon ?? ""),
  }));
}

function mapSupportCta(remote?: Record<string, unknown>): HomepageSupportCta {
  return {
    enabled: remote?.enabled === true,
    eyebrow: String(remote?.eyebrow ?? ""),
    title: String(remote?.title ?? ""),
    subtitle: String(remote?.subtitle ?? ""),
    callEnabled: remote?.call_enabled !== false,
    callLabel: String(remote?.call_label ?? "Call support"),
    callHref: sanitizePublicActionHref(remote?.call_href ? String(remote.call_href) : null),
    chatEnabled: remote?.chat_enabled !== false,
    chatLabel: String(remote?.chat_label ?? "Get support"),
    chatHref: sanitizePublicActionHref(remote?.chat_href ? String(remote.chat_href) : null),
    image: remote?.image ? String(remote.image) : null,
  };
}

function sanitizePublicActionHref(href: string | null): string | null {
  if (!href) return null;
  const trimmed = href.trim();
  if (trimmed === "" || trimmed === "#") return null;
  const lower = trimmed.toLowerCase();
  if (lower.startsWith("javascript:")) return null;
  if (lower.startsWith("tel:") || lower.startsWith("mailto:") || trimmed.startsWith("/")) {
    return trimmed;
  }
  try {
    const url = new URL(trimmed);
    const host = url.hostname.toLowerCase();
    const isPrivate =
      host === "127.0.0.1" || host === "localhost" || host.endsWith(".local");
    if (isPrivate) {
      const path = url.pathname || "/";
      if (path.toLowerCase().startsWith("/tel:") || path.toLowerCase().startsWith("/mailto:")) {
        return path.slice(1) + url.search + url.hash;
      }
      return path + url.search + url.hash;
    }
    return trimmed;
  } catch {
    return trimmed.startsWith("/") ? trimmed : `/${trimmed.replace(/^\/+/, "")}`;
  }
}

function mapTrustChips(chips: Array<{ label?: string }> = []): HomepageTrustChip[] {
  return chips
    .map((chip) => ({ label: String(chip.label ?? "").trim() }))
    .filter((chip) => chip.label !== "");
}

function fixtureHomepage(): HomepageContent {
  return {
    source: "fixture",
    hero: {
      eyebrow: "Pakistan's trusted OTA",
      headline: "Explore the world with",
      headlineHighlight: "JetPakistan",
      subtitle:
        "Book flights with confidence — secure fares, dedicated support, and routes tailored for travelers across Pakistan and beyond.",
      searchVisible: true,
      image: { url: HERO_FALLBACK_IMAGE, alt: HERO_FALLBACK_ALT },
    },
    trustChips: BENEFIT_FIXTURES.map((item) => ({ label: item.title })),
    routes: {
      enabled: true,
      eyebrow: "",
      title: "Destinations on the Rise",
      subtitle: "",
      ctaText: "",
      ctaUrl: "",
      items: DESTINATION_FIXTURES.map((item) => ({
        id: item.id,
        from: item.city,
        to: item.country,
        priceLabel: item.label,
        searchUrl: "/flights/results",
        image: item.image,
        imageAlt: item.imageAlt,
      })),
    },
    destinations: {
      enabled: false,
      eyebrow: "",
      title: "",
      subtitle: "",
      ctaText: "",
      ctaUrl: "",
      items: [],
    },
    featuredDeals: {
      enabled: true,
      eyebrow: "",
      title: "Featured Offers",
      subtitle: "",
      ctaText: "",
      ctaUrl: "",
      items: FEATURED_OFFER_FIXTURES.map((offer) => ({
        id: offer.id,
        airline: offer.badge ?? "",
        from: offer.title,
        to: offer.subtitle,
        depart: "",
        arrive: "",
        duration: "",
        stops: 0,
        priceLabel: offer.samplePrice ?? "",
        image: offer.image,
        imageAlt: offer.imageAlt,
      })),
    },
    whyBook: {
      enabled: true,
      eyebrow: "",
      title: "Why JetPakistan",
      subtitle: "",
      ctaText: "",
      ctaUrl: "",
      cards: VALUE_PROPOSITION_FIXTURES.map((item) => ({
        id: item.id,
        num: "",
        title: item.title,
        text: item.description,
        icon: item.icon,
      })),
    },
    supportCta: {
      enabled: true,
      eyebrow: "",
      title: "Need help planning your trip?",
      subtitle: "Our support team is ready to assist with routes, group fares, and booking questions.",
      callEnabled: false,
      callLabel: "Contact Support",
      callHref: "/support",
      chatEnabled: true,
      chatLabel: "Contact Support",
      chatHref: "/support",
      image: null,
    },
    featureBoard: { enabled: false, items: [] },
  };
}

function emptyHomepage(): HomepageContent {
  return {
    source: "empty",
    hero: emptyHero(),
    trustChips: [],
    routes: { ...mapSectionHeader(), items: [] },
    destinations: { ...mapSectionHeader(), items: [] },
    featuredDeals: { ...mapSectionHeader(), items: [] },
    whyBook: { ...mapSectionHeader(), cards: [] },
    supportCta: mapSupportCta({ enabled: false }),
    featureBoard: { enabled: false, items: [] },
  };
}

function mapRemote(remote: RemoteHomepage): HomepageContent {
  const hasCms = remote.source === "cms";

  return {
    source: resolveContentSource(hasCms),
    hero: mapHero(remote.hero),
    trustChips: mapTrustChips(remote.trust_chips),
    routes: { ...mapSectionHeader(remote.routes), items: mapRoutes(remote.routes?.items) },
    destinations: {
      ...mapSectionHeader(remote.destinations),
      items: mapDestinations(remote.destinations?.items),
    },
    featuredDeals: {
      ...mapSectionHeader(remote.featured_deals),
      items: mapFeaturedDeals(remote.featured_deals?.items),
    },
    whyBook: {
      ...mapSectionHeader(remote.why_book),
      cards: mapWhyCards(remote.why_book?.cards),
    },
    supportCta: mapSupportCta(remote.support_cta),
    featureBoard: {
      enabled: remote.feature_board?.enabled === true,
      items: (remote.feature_board?.items ?? []).map((item, index) => ({
        id: String(item.id ?? `stat-${index}`),
        value: String(item.value ?? ""),
        label: String(item.label ?? ""),
      })),
    },
  };
}

function resolveHomepageApiUrl(): string {
  if (typeof window !== "undefined") {
    return laravelApiPath("/api/public/content/homepage");
  }

  const laravelBase = (
    process.env.LARAVEL_URL ??
    process.env.NEXT_PUBLIC_LARAVEL_URL ??
    "http://127.0.0.1:8000"
  ).replace(/\/$/, "");

  return `${laravelBase}/api/public/content/homepage`;
}

export const HomepageContentService = {
  heroFallbackImage: HERO_FALLBACK_IMAGE,
  heroFallbackAlt: HERO_FALLBACK_ALT,

  async getHomepage(): Promise<HomepageContent> {
    try {
      const response = await fetchWithTimeout(resolveHomepageApiUrl(), {
        headers: { Accept: "application/json" },
        next: { revalidate: 120 },
      });

      if (!response.ok) {
        return allowContentFixtures() ? fixtureHomepage() : emptyHomepage();
      }

      const remote = (await response.json()) as RemoteHomepage;
      if (remote.source === "empty") {
        return allowContentFixtures() ? fixtureHomepage() : emptyHomepage();
      }

      return mapRemote(remote);
    } catch {
      return allowContentFixtures() ? fixtureHomepage() : emptyHomepage();
    }
  },
};
