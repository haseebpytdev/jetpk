import { BENEFIT_FIXTURES } from "@/features/home/fixtures/benefits";
import { DESTINATION_FIXTURES } from "@/features/home/fixtures/destinations";
import { INSPIRATION_FIXTURES, VALUE_PROPOSITION_FIXTURES } from "@/features/home/fixtures/inspiration";
import { FEATURED_OFFER_FIXTURES } from "@/features/home/fixtures/offers";
import { laravelApiPath } from "@/services/flight-search";
import { allowContentFixtures, resolveContentSource } from "@/features/public-content/utils/content-policy";
import { fetchWithTimeout } from "@/features/public-content/utils/laravel-api";
import type {
  HomepageContent,
  HomepageDestinationCard,
  HomepageFeaturedDeal,
  HomepageHeroContent,
  HomepageInspirationCard,
  HomepageOfferCard,
  HomepageRouteCard,
  HomepageSupportCta,
  HomepageTrustChip,
  HomepageWhyCard,
} from "../types/homepage";

type RemoteHomepage = {
  source: "cms" | "empty";
  hero?: Record<string, unknown>;
  trust_chips?: Array<{ label?: string; description?: string; icon?: string }>;
  routes?: RemoteSection & { items?: Array<Record<string, unknown>> };
  destinations?: RemoteSection & { items?: Array<Record<string, unknown>> };
  featured_deals?: RemoteSection & { items?: Array<Record<string, unknown>> };
  promo_offers?: RemoteSection & { items?: Array<Record<string, unknown>> };
  why_book?: RemoteSection & { cards?: Array<Record<string, unknown>> };
  support_cta?: Record<string, unknown>;
  inspiration?: RemoteSection & { items?: Array<Record<string, unknown>> };
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
  };
}

function mapHero(remote?: Record<string, unknown>): HomepageHeroContent {
  const image = remote?.image as { url?: string; alt?: string } | null | undefined;
  return {
    eyebrow: String(remote?.eyebrow ?? ""),
    headline: String(remote?.headline ?? ""),
    headlineHighlight: String(remote?.headline_highlight ?? ""),
    subtitle: String(remote?.subtitle ?? ""),
    searchVisible: remote?.search_visible !== false,
    image: image?.url
      ? { url: image.url, alt: String(image.alt ?? "JetPakistan flights") }
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
    fromCode: item.from_code ? String(item.from_code) : undefined,
    toCode: item.to_code ? String(item.to_code) : undefined,
    priceLabel: String(item.price_label ?? item.price ?? ""),
    searchUrl: String(item.search_url ?? ""),
    badge: item.badge ? String(item.badge) : undefined,
    image: item.image ? String(item.image) : null,
    imageAlt: item.image_alt ? String(item.image_alt) : undefined,
    airline: item.airline ? String(item.airline) : undefined,
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
  }));
}

function mapPromoOffers(items: Array<Record<string, unknown>> = []): HomepageOfferCard[] {
  return items.map((item, index) => ({
    id: String(item.id ?? `offer-${index}`),
    title: String(item.title ?? ""),
    subtitle: item.subtitle ? String(item.subtitle) : undefined,
    discountValue: String(item.discount_value ?? item.discount ?? ""),
    discountCaption: item.discount_caption ? String(item.discount_caption) : undefined,
    ctaLabel: String(item.cta_label ?? item.cta ?? "Book Now"),
    ctaHref: String(item.cta_href ?? item.cta_url ?? "/"),
    image: item.image ? String(item.image) : null,
    imageAlt: item.image_alt ? String(item.image_alt) : undefined,
    theme: item.theme as HomepageOfferCard["theme"],
  }));
}

function mapInspiration(items: Array<Record<string, unknown>> = []): HomepageInspirationCard[] {
  return items.map((item, index) => ({
    id: String(item.id ?? `inspiration-${index}`),
    category: String(item.category ?? ""),
    title: String(item.title ?? ""),
    publishedAt: item.published_at ? String(item.published_at) : undefined,
    readingTime: item.reading_time ? String(item.reading_time) : undefined,
    image: item.image ? String(item.image) : null,
    imageAlt: item.image_alt ? String(item.image_alt) : undefined,
    href: item.href ? String(item.href) : null,
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
    callHref: remote?.call_href ? String(remote.call_href) : null,
    chatEnabled: remote?.chat_enabled !== false,
    chatLabel: String(remote?.chat_label ?? "Get support"),
    chatHref: remote?.chat_href ? String(remote.chat_href) : null,
    image: remote?.image ? String(remote.image) : null,
  };
}

function mapTrustChips(chips: Array<{ label?: string; description?: string; icon?: string }> = []): HomepageTrustChip[] {
  return chips
    .map((chip) => ({
      label: String(chip.label ?? "").trim(),
      description: chip.description ? String(chip.description) : undefined,
      icon: chip.icon ? String(chip.icon) : undefined,
    }))
    .filter((chip) => chip.label !== "");
}

function fixtureHomepage(): HomepageContent {
  return {
    source: "fixture",
    hero: {
      eyebrow: "",
      headline: "Explore the World with",
      headlineHighlight: "JetPakistan",
      subtitle:
        "Find the best flight deals to your dream destinations. Book with confidence and fly with ease.",
      searchVisible: true,
      image: null,
    },
    trustChips: BENEFIT_FIXTURES.map((item) => ({
      label: item.title,
      description: item.description,
      icon: item.icon,
    })),
    routes: {
      enabled: true,
      eyebrow: "",
      title: "Destinations on the Rise",
      subtitle: "Popular routes with competitive fares",
      ctaText: "View all destinations",
      ctaUrl: "/",
      items: DESTINATION_FIXTURES.map((item) => ({
        id: item.id,
        from: item.city,
        to: item.country,
        fromCode: item.fromCode,
        toCode: item.toCode,
        priceLabel: item.label,
        searchUrl: "/flights/results",
        image: item.image,
        imageAlt: item.imageAlt,
        airline: item.airline,
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
      enabled: false,
      eyebrow: "",
      title: "",
      subtitle: "",
      ctaText: "",
      ctaUrl: "",
      items: [],
    },
    promoOffers: {
      enabled: true,
      eyebrow: "",
      title: "Featured Offers",
      subtitle: "Limited time deals on top destinations",
      ctaText: "View all offers",
      ctaUrl: "/",
      items: FEATURED_OFFER_FIXTURES.map((offer) => ({
        id: offer.id,
        title: offer.title,
        subtitle: offer.subtitle,
        discountValue: offer.discountValue ?? "",
        discountCaption: offer.badge,
        ctaLabel: offer.cta,
        ctaHref: "/flights/results",
        image: offer.image,
        imageAlt: offer.imageAlt,
        theme: offer.theme,
      })),
    },
    whyBook: {
      enabled: true,
      eyebrow: "",
      title: "Why JetPakistan?",
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
      title: "Need Help? We're Here For You",
      subtitle: "Our support team is available 24/7 to assist with your travel needs.",
      callEnabled: false,
      callLabel: "Contact Support",
      callHref: "/support",
      chatEnabled: true,
      chatLabel: "Contact Support",
      chatHref: "/support",
      image: null,
    },
    inspiration: {
      enabled: true,
      eyebrow: "",
      title: "Travel Inspiration",
      subtitle: "Stories, guides, and tips for your next adventure",
      ctaText: "View all articles",
      ctaUrl: "/",
      items: INSPIRATION_FIXTURES.map((item) => ({
        id: item.id,
        category: item.category,
        title: item.title,
        publishedAt: item.publishedAt,
        readingTime: item.readingTime,
        image: item.image,
        imageAlt: item.imageAlt,
        href: null,
      })),
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
    promoOffers: { ...mapSectionHeader(), items: [] },
    whyBook: { ...mapSectionHeader(), cards: [] },
    supportCta: mapSupportCta({ enabled: false }),
    inspiration: { ...mapSectionHeader(), items: [] },
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
    promoOffers: {
      ...mapSectionHeader(remote.promo_offers),
      items: mapPromoOffers(remote.promo_offers?.items),
    },
    whyBook: {
      ...mapSectionHeader(remote.why_book),
      cards: mapWhyCards(remote.why_book?.cards),
    },
    supportCta: mapSupportCta(remote.support_cta),
    inspiration: {
      ...mapSectionHeader(remote.inspiration),
      items: mapInspiration(remote.inspiration?.items),
    },
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

export const HomepageContentService = {
  async getHomepage(): Promise<HomepageContent> {
    try {
      const response = await fetchWithTimeout(laravelApiPath("/api/public/content/homepage"), {
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
