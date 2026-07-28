export type BenefitItem = {
  id: string;
  title: string;
  description: string;
  icon: "shield" | "headset" | "fare" | "pakistan";
};

export type DestinationCard = {
  id: string;
  city: string;
  country: string;
  label: string;
  image: string;
  imageAlt: string;
};

export type FeaturedOffer = {
  id: string;
  title: string;
  subtitle: string;
  badge?: string;
  cta: string;
  image: string;
  imageAlt: string;
  samplePrice?: string;
};

export type ValueProposition = {
  id: string;
  title: string;
  description: string;
  icon: "expertise" | "transparent" | "support" | "secure";
};

export type InspirationCard = {
  id: string;
  title: string;
  category: string;
  excerpt: string;
  image: string;
  imageAlt: string;
};
