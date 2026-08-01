/**
 * Neutral development fixtures for JP-PUBLIC-NEXT-THEME-03 homepage review.
 * Not production claims — consumed only by the isolated review route.
 */

export type BenefitFixture = {
  id: string;
  title: string;
  description: string;
  icon: string;
};

export type DestinationFixture = {
  id: string;
  route: string;
  priceLabel: string;
  airlineLabel: string;
  imageVariant: number;
};

export type OfferFixture = {
  id: string;
  title: string;
  discount: string;
  caption: string;
  variant: 1 | 2 | 3;
};

export type WhyFixture = {
  id: string;
  title: string;
  description: string;
  icon: string;
};

export type InspirationFixture = {
  id: string;
  category: string;
  title: string;
  meta: string;
  imageVariant: number;
};

export const HOMEPAGE_V2_HERO = {
  title: "Explore the World",
  titleAccent: "with JetPakistan",
  supporting: "Find flight options for your next trip. Review layout and composition only.",
} as const;

export const HOMEPAGE_V2_SEARCH = {
  tabs: ["One Way", "Round Trip", "Multi-City"] as const,
  origin: { code: "AAA", city: "Review origin", airport: "Fixture airport A" },
  destination: { code: "BBB", city: "Review destination", airport: "Fixture airport B" },
  departure: { date: "Fixture date", day: "Review weekday" },
  passengers: { count: "1 Passenger", cabin: "Economy" },
  cta: "Search Flights",
} as const;

export const HOMEPAGE_V2_BENEFITS: BenefitFixture[] = [
  { id: "b1", title: "Best Price Guarantee", description: "Fixture benefit description A", icon: "◉" },
  { id: "b2", title: "Secure Booking", description: "Fixture benefit description B", icon: "♢" },
  { id: "b3", title: "24/7 Customer Support", description: "Fixture benefit description C", icon: "◌" },
  { id: "b4", title: "Easy & Fast Booking", description: "Fixture benefit description D", icon: "▣" },
];

export const HOMEPAGE_V2_DESTINATIONS: DestinationFixture[] = [
  { id: "d1", route: "Route A → Route B", priceLabel: "Fixture price", airlineLabel: "Review airline A", imageVariant: 1 },
  { id: "d2", route: "Route C → Route D", priceLabel: "Fixture price", airlineLabel: "Review airline B", imageVariant: 2 },
  { id: "d3", route: "Route E → Route F", priceLabel: "Fixture price", airlineLabel: "Review airline C", imageVariant: 3 },
  { id: "d4", route: "Route G → Route H", priceLabel: "Fixture price", airlineLabel: "Review airline D", imageVariant: 4 },
  { id: "d5", route: "Route I → Route J", priceLabel: "Fixture price", airlineLabel: "Review airline E", imageVariant: 5 },
];

export const HOMEPAGE_V2_OFFERS: OfferFixture[] = [
  { id: "o1", title: "Offer fixture A", discount: "20% OFF", caption: "Review offer caption A", variant: 1 },
  { id: "o2", title: "Offer fixture B", discount: "15% OFF", caption: "Review offer caption B", variant: 2 },
  { id: "o3", title: "Offer fixture C", discount: "10% OFF", caption: "Review offer caption C", variant: 3 },
];

export const HOMEPAGE_V2_WHY: WhyFixture[] = [
  { id: "w1", title: "Best Prices", description: "Fixture reason copy A", icon: "◉" },
  { id: "w2", title: "Trusted & Secure", description: "Fixture reason copy B", icon: "♢" },
  { id: "w3", title: "Flexible Options", description: "Fixture reason copy C", icon: "⇄" },
  { id: "w4", title: "Generous Baggage", description: "Fixture reason copy D", icon: "▣" },
  { id: "w5", title: "On-Time Performance", description: "Fixture reason copy E", icon: "◌" },
];

export const HOMEPAGE_V2_SUPPORT = {
  title: "Need Help? We're Here for You",
  description: "Support callout fixture for layout review. Not an operational support channel.",
  cta: "Contact Support",
} as const;

export const HOMEPAGE_V2_INSPIRATION: InspirationFixture[] = [
  { id: "i1", category: "TRAVEL GUIDE", title: "Inspiration fixture title A", meta: "Review date · 5 min read", imageVariant: 1 },
  { id: "i2", category: "TRAVEL TIPS", title: "Inspiration fixture title B", meta: "Review date · 5 min read", imageVariant: 2 },
  { id: "i3", category: "DESTINATION", title: "Inspiration fixture title C", meta: "Review date · 5 min read", imageVariant: 3 },
  { id: "i4", category: "CULTURE", title: "Inspiration fixture title D", meta: "Review date · 5 min read", imageVariant: 4 },
];

export const HOMEPAGE_V2_NAV = [
  { label: "Flights", hasDropdown: true },
  { label: "Hotels", hasDropdown: false },
  { label: "Groups", hasDropdown: false, badge: "New" },
  { label: "Offers", hasDropdown: false },
  { label: "Travel Services", hasDropdown: true },
  { label: "Support", hasDropdown: true },
] as const;

export const HOMEPAGE_V2_FOOTER_COLUMNS = [
  {
    title: "Explore",
    links: ["Flights", "Hotels", "Groups", "Offers"],
  },
  {
    title: "Company",
    links: ["About Us", "Careers", "Press", "Blog"],
  },
  {
    title: "Support",
    links: ["Help Center", "Contact Us", "Manage Booking", "FAQs"],
  },
  {
    title: "Legal",
    links: ["Terms & Conditions", "Privacy Policy", "Cookie Policy", "Sitemap"],
  },
] as const;
