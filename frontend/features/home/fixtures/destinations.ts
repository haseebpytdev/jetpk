import type { DestinationCard } from "../types";

/** Sample fare labels — presentation only, not live supplier prices. */
export const DESTINATION_FIXTURES: DestinationCard[] = [
  {
    id: "dubai",
    city: "Dubai",
    country: "UAE",
    label: "Sample from PKR 45,000",
    image: "/images/home/destination-dubai.svg",
    imageAlt: "Dubai skyline illustration",
  },
  {
    id: "jeddah",
    city: "Jeddah",
    country: "Saudi Arabia",
    label: "Sample from PKR 52,000",
    image: "/images/home/destination-jeddah.svg",
    imageAlt: "Jeddah coastal illustration",
  },
  {
    id: "london",
    city: "London",
    country: "United Kingdom",
    label: "Sample from PKR 185,000",
    image: "/images/home/destination-london.svg",
    imageAlt: "London landmarks illustration",
  },
  {
    id: "istanbul",
    city: "Istanbul",
    country: "Turkey",
    label: "Sample from PKR 68,000",
    image: "/images/home/destination-istanbul.svg",
    imageAlt: "Istanbul skyline illustration",
  },
];
