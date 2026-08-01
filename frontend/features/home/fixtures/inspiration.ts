import type { ValueProposition, InspirationCard } from "../types";

export const VALUE_PROPOSITION_FIXTURES: ValueProposition[] = [
  {
    id: "prices",
    title: "Best Prices",
    description: "Competitive fares across major routes.",
    icon: "fare",
  },
  {
    id: "secure",
    title: "Trusted & Secure",
    description: "Protected payments and verified inventory.",
    icon: "secure",
  },
  {
    id: "flexible",
    title: "Flexible Options",
    description: "Change plans with clear fare rules.",
    icon: "flexible",
  },
  {
    id: "baggage",
    title: "Generous Baggage",
    description: "Clear baggage allowances on every fare.",
    icon: "baggage",
  },
  {
    id: "ontime",
    title: "On-Time Performance",
    description: "Reliable schedules from trusted carriers.",
    icon: "ontime",
  },
];

export const INSPIRATION_FIXTURES: InspirationCard[] = [
  {
    id: "turkey-guide",
    title: "Top 10 Places to Visit in Turkey",
    category: "TRAVEL GUIDE",
    excerpt: "Discover the best destinations across Turkey.",
    publishedAt: "March 15, 2026",
    readingTime: "8 min read",
    image: "/images/home/inspiration-gcc.svg",
    imageAlt: "Turkey travel guide",
  },
  {
    id: "umrah-prep",
    title: "Planning Your Sacred Journey",
    category: "TRAVEL TIPS",
    excerpt: "Essential tips for group and individual travel to Saudi Arabia.",
    publishedAt: "March 10, 2026",
    readingTime: "6 min read",
    image: "/images/home/inspiration-umrah.svg",
    imageAlt: "Umrah travel tips",
  },
  {
    id: "summer-europe",
    title: "Summer in Europe",
    category: "DESTINATION",
    excerpt: "Early booking ideas for UK and Schengen routes.",
    publishedAt: "March 5, 2026",
    readingTime: "5 min read",
    image: "/images/home/inspiration-europe.svg",
    imageAlt: "European summer travel",
  },
  {
    id: "gcc-winter",
    title: "Winter Escapes to the Gulf",
    category: "TRAVEL GUIDE",
    excerpt: "Mild weather and family-friendly destinations across the GCC.",
    publishedAt: "February 28, 2026",
    readingTime: "7 min read",
    image: "/images/home/inspiration-gcc.svg",
    imageAlt: "GCC winter travel",
  },
];
