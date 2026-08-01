import type { FeaturedOffer } from "../types";

export const FEATURED_OFFER_FIXTURES: FeaturedOffer[] = [
  {
    id: "summer-saver",
    title: "Summer Saver",
    subtitle: "Book your summer getaway now and save big on flights to top destinations.",
    badge: "UP TO",
    discountValue: "20% OFF",
    cta: "Book Now",
    image: "/images/home/offer-gcc.svg",
    imageAlt: "Summer beach destination",
    theme: "summer",
  },
  {
    id: "weekend-getaway",
    title: "Weekend Getaway",
    subtitle: "Escape the city with exclusive weekend flight deals.",
    badge: "UP TO",
    discountValue: "15% OFF",
    cta: "Book Now",
    image: "/images/home/offer-uk.svg",
    imageAlt: "City skyline at night",
    theme: "weekend",
  },
  {
    id: "family-travel",
    title: "Family Travel Deal",
    subtitle: "Special fares for families traveling together.",
    badge: "UP TO",
    discountValue: "10% OFF",
    cta: "Book Now",
    image: "/images/home/offer-domestic.svg",
    imageAlt: "Family travel",
    theme: "family",
  },
];
