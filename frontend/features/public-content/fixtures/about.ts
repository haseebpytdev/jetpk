import type { PublicPage } from "../types";
import { SITE_CONTACT_FIXTURE } from "./site-contact";

/** Fixture boundary — mirrors Laravel bootstrap about content when CMS is unpublished. */
export const ABOUT_PAGE_FIXTURE: PublicPage = {
  pageKey: "about",
  source: "fixture",
  hero: {
    kicker: "About JetPakistan",
    title: "Cheap flights and secure online booking for Pakistan",
    description:
      "JetPakistan helps travellers discover low fares, compare airlines, and complete domestic and international flight bookings online with confidence.",
  },
  sections: [
    {
      id: "story",
      title: "Our story",
      body: "JetPakistan brings airline options together in one place so travellers across Pakistan can compare routes, cabin classes, and total price before booking.",
    },
    {
      id: "mission",
      title: "Our mission",
      body: "Make flight search and booking straightforward with transparent PKR pricing, secure checkout, and human support when plans change.",
    },
    {
      id: "values",
      title: "What we value",
      items: [
        {
          id: "value-1",
          title: "Transparent fares",
          body: "Clear pricing at checkout with no surprise charges.",
        },
        {
          id: "value-2",
          title: "Secure booking",
          body: "Protected checkout and support for itinerary questions.",
        },
        {
          id: "value-3",
          title: "Human support",
          body: "Reach our team by phone, WhatsApp, or email when you need help.",
        },
      ],
    },
    {
      id: "journey",
      title: "Built for Pakistan travellers",
      body: "From Lahore, Karachi, and Islamabad to Dubai, Jeddah, and beyond — search domestic and international routes with a Pakistan-focused experience.",
    },
    {
      id: "why",
      title: "Why JetPakistan",
      items: [
        {
          id: "why-1",
          title: "Lowest fare discovery",
          body: "Search routes from Pakistan and compare airline options side by side.",
        },
        {
          id: "why-2",
          title: "Booking confidence",
          body: "Pick your route, choose dates, select travellers, and confirm with support when needed.",
        },
        {
          id: "why-3",
          title: "Dedicated support",
          body: "Help with booking changes, invoices, and travel questions.",
        },
      ],
    },
  ],
  contact: SITE_CONTACT_FIXTURE,
  cta: {
    primaryLabel: "Search flights",
    primaryHref: "/#main-content",
    secondaryLabel: "Contact support",
    secondaryHref: "/support",
  },
  seo: {
    title: "About us — JetPakistan",
    description:
      "Learn about JetPakistan, Pakistan-focused online flight booking with transparent PKR fares and dedicated support.",
    robots: "index,follow",
  },
};
