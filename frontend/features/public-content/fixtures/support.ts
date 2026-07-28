import type { SupportPageContent } from "../types";
import { SITE_CONTACT_FIXTURE } from "./site-contact";

export const SUPPORT_TOPICS_FIXTURE = [
  {
    id: "topic-search",
    title: "Flight search and booking",
    summary: "Search routes, compare fares, and complete new bookings.",
    category: "flight-search",
    keywords: ["search", "book", "fare", "flight", "itinerary"],
  },
  {
    id: "topic-payments",
    title: "Payments",
    summary: "Payment methods, confirmations, and invoice questions.",
    category: "payments",
    keywords: ["payment", "invoice", "receipt", "card", "bank"],
  },
  {
    id: "topic-pnr",
    title: "Booking status and PNR",
    summary: "Track booking status, e-tickets, and reference lookups.",
    category: "pnr",
    keywords: ["pnr", "status", "reference", "e-ticket", "lookup"],
  },
  {
    id: "topic-refunds",
    title: "Cancellations and refunds",
    summary: "Change requests, cancellations, and refund guidance.",
    category: "refunds",
    keywords: ["cancel", "refund", "change", "void"],
  },
  {
    id: "topic-account",
    title: "Account and login",
    summary: "Sign in, registration, and account access help.",
    category: "account",
    keywords: ["login", "account", "password", "register"],
  },
  {
    id: "topic-agent",
    title: "Agent support",
    summary: "Partnership applications and agent dashboard access.",
    category: "agent",
    keywords: ["agent", "agency", "partner", "b2b"],
  },
  {
    id: "topic-groups",
    title: "Group Ticketing",
    summary: "Group departures, block seats, and series inventory.",
    category: "groups",
    keywords: ["group", "umrah", "series", "block seat"],
  },
] as const;

/** Fixture boundary — presentation topics; ticket submission uses Laravel support.store. */
export const SUPPORT_PAGE_FIXTURE: SupportPageContent = {
  source: "fixture",
  hero: {
    kicker: "Support & contact",
    title: "Flight booking help, 24/7",
    description:
      "Get assistance with online ticket booking, fare questions, payments, e-tickets, changes, and online check-in.",
  },
  topics: SUPPORT_TOPICS_FIXTURE.map((topic) => ({
    ...topic,
    keywords: [...topic.keywords],
  })),
  departments: [
    {
      id: "dept-booking",
      title: "Booking assistance",
      body: "New bookings, itinerary changes, and passenger detail updates.",
    },
    {
      id: "dept-payments",
      title: "Payments & confirmation",
      body: "Payment proof, booking confirmation, and invoice questions.",
    },
    {
      id: "dept-checkin",
      title: "Online check-in",
      body: "Guidance for airline check-in and boarding pass access.",
    },
  ],
  contact: SITE_CONTACT_FIXTURE,
  faqTeaser: {
    title: "FAQ",
    body: "Browse quick answers before opening a support request.",
    linkLabel: "View full help centre",
    linkHref: "/faq",
  },
  seo: {
    title: "Support & contact — JetPakistan",
    description: "Contact JetPakistan support for booking help, payments, e-tickets, and travel questions.",
    robots: "index,follow",
  },
};
