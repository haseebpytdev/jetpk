import type { DestinationCard } from "../types";

/** Sample fare labels — presentation only, not live supplier prices. */
export const DESTINATION_FIXTURES: DestinationCard[] = [
  {
    id: "dubai",
    city: "Dubai",
    country: "UAE",
    label: "Sample from PKR 45,000",
    image: "/images/home/destination-dubai.jpg",
    imageAlt: "Dubai skyline at sunset",
  },
  {
    id: "jeddah",
    city: "Jeddah",
    country: "Saudi Arabia",
    label: "Sample from PKR 52,000",
    image: "/images/home/destination-jeddah.jpg",
    imageAlt: "Jeddah Red Sea coastline",
  },
  {
    id: "london",
    city: "London",
    country: "United Kingdom",
    label: "Sample from PKR 185,000",
    image: "/images/home/destination-london.jpg",
    imageAlt: "London cityscape along the Thames",
  },
  {
    id: "istanbul",
    city: "Istanbul",
    country: "Turkey",
    label: "Sample from PKR 68,000",
    image: "/images/home/destination-istanbul.jpg",
    imageAlt: "Istanbul skyline with historic architecture",
  },
];
