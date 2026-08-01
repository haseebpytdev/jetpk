export type HomepageHeroContent = {
  eyebrow: string;
  headline: string;
  headlineHighlight: string;
  subtitle: string;
  searchVisible: boolean;
  image: { url: string; alt: string } | null;
};

export type HomepageTrustChip = {
  label: string;
  description?: string;
  icon?: string;
};

export type HomepageRouteCard = {
  id: string;
  from: string;
  to: string;
  fromCode?: string;
  toCode?: string;
  priceLabel: string;
  searchUrl: string;
  badge?: string;
  image?: string | null;
  imageAlt?: string;
  airline?: string;
};

export type HomepageDestinationCard = {
  id: string;
  code: string;
  title: string;
  country?: string;
  text?: string;
  image: string | null;
  priceLabel: string;
  href: string | null;
};

export type HomepageFeaturedDeal = {
  id: string;
  airline: string;
  from: string;
  to: string;
  depart: string;
  arrive: string;
  duration: string;
  stops: number;
  priceLabel: string;
};

export type HomepageOfferCard = {
  id: string;
  title: string;
  subtitle?: string;
  discountValue: string;
  discountCaption?: string;
  ctaLabel: string;
  ctaHref: string;
  image: string | null;
  imageAlt?: string;
  theme?: "summer" | "weekend" | "family";
};

export type HomepageWhyCard = {
  id: string;
  num: string;
  title: string;
  text: string;
  icon: string;
};

export type HomepageInspirationCard = {
  id: string;
  category: string;
  title: string;
  publishedAt?: string;
  readingTime?: string;
  image: string | null;
  imageAlt?: string;
  href?: string | null;
};

export type HomepageSectionHeader = {
  enabled: boolean;
  eyebrow: string;
  title: string;
  subtitle: string;
  ctaText: string;
  ctaUrl: string;
};

export type HomepageSupportCta = {
  enabled: boolean;
  eyebrow: string;
  title: string;
  subtitle: string;
  callEnabled: boolean;
  callLabel: string;
  callHref: string | null;
  chatEnabled: boolean;
  chatLabel: string;
  chatHref: string | null;
  image: string | null;
};

export type HomepageFeatureStat = {
  id: string;
  value: string;
  label: string;
};

export type HomepageContent = {
  source: "cms" | "fixture" | "empty";
  hero: HomepageHeroContent;
  trustChips: HomepageTrustChip[];
  routes: HomepageSectionHeader & { items: HomepageRouteCard[] };
  destinations: HomepageSectionHeader & { items: HomepageDestinationCard[] };
  featuredDeals: HomepageSectionHeader & { items: HomepageFeaturedDeal[] };
  promoOffers: HomepageSectionHeader & { items: HomepageOfferCard[] };
  whyBook: HomepageSectionHeader & { cards: HomepageWhyCard[] };
  supportCta: HomepageSupportCta;
  inspiration: HomepageSectionHeader & { items: HomepageInspirationCard[] };
  featureBoard: { enabled: boolean; items: HomepageFeatureStat[] };
};
