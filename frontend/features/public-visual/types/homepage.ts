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
};

export type HomepageRouteCard = {
  id: string;
  from: string;
  to: string;
  priceLabel: string;
  searchUrl: string;
  badge?: string;
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

export type HomepageWhyCard = {
  id: string;
  num: string;
  title: string;
  text: string;
  icon: string;
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
  whyBook: HomepageSectionHeader & { cards: HomepageWhyCard[] };
  supportCta: HomepageSupportCta;
  featureBoard: { enabled: boolean; items: HomepageFeatureStat[] };
};
