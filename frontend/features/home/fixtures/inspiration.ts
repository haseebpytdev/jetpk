import type { ValueProposition, InspirationCard } from "../types";

export const VALUE_PROPOSITION_FIXTURES: ValueProposition[] = [
  {
    id: "expertise",
    title: "Local expertise",
    description: "Route knowledge tailored to Pakistani travelers and diaspora needs.",
    icon: "expertise",
  },
  {
    id: "transparent",
    title: "Transparent process",
    description: "Clear fare breakdowns and straightforward booking steps.",
    icon: "transparent",
  },
  {
    id: "support",
    title: "Trusted support",
    description: "Responsive help when plans change or questions arise.",
    icon: "support",
  },
  {
    id: "secure",
    title: "Secure travel experience",
    description: "Protected payments and verified airline inventory.",
    icon: "secure",
  },
];

export const INSPIRATION_FIXTURES: InspirationCard[] = [
  {
    id: "winter-gcc",
    title: "Winter escapes to the Gulf",
    category: "Destination inspiration",
    excerpt: "Mild weather and family-friendly destinations across the GCC.",
    image: "/images/home/inspiration-gcc.svg",
    imageAlt: "",
  },
  {
    id: "umrah-prep",
    title: "Planning your sacred journey",
    category: "Travel guides",
    excerpt: "Essential tips for group and individual travel to Saudi Arabia.",
    image: "/images/home/inspiration-umrah.svg",
    imageAlt: "",
  },
  {
    id: "summer-europe",
    title: "Summer in Europe",
    category: "Seasonal travel ideas",
    excerpt: "Early booking ideas for UK and Schengen routes.",
    image: "/images/home/inspiration-europe.svg",
    imageAlt: "",
  },
];
