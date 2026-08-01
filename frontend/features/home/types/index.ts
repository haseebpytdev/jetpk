export type BenefitItem = {
  id: string;
  title: string;
  description: string;
  icon: "shield" | "headset" | "fare" | "pakistan" | "spark";
};

export type DestinationCard = {
  id: string;
  city: string;
  country: string;
  fromCode?: string;
  toCode?: string;
  label: string;
  airline?: string;
  image: string;
  imageAlt: string;
};

export type FeaturedOffer = {
  id: string;
  title: string;
  subtitle: string;
  badge?: string;
  discountValue?: string;
  cta: string;
  image: string;
  imageAlt: string;
  theme?: "summer" | "weekend" | "family";
  samplePrice?: string;
};

export type ValueProposition = {
  id: string;
  title: string;
  description: string;
  icon: "expertise" | "transparent" | "support" | "secure" | "fare" | "flexible" | "baggage" | "ontime";
};

export type InspirationCard = {
  id: string;
  title: string;
  category: string;
  excerpt: string;
  publishedAt?: string;
  readingTime?: string;
  image: string;
  imageAlt: string;
};
