import type { LegalDocument } from "../types";

/** Fixture boundary — mirrors Laravel bootstrap legal content when CMS is unpublished. */
export const TERMS_DOCUMENT_FIXTURE: LegalDocument = {
  source: "fixture",
  title: "Terms of service",
  effectiveDate: "2026-01-01",
  intro: "These terms govern your use of the JetPakistan online travel platform.",
  sections: [
    {
      id: "terms-1",
      heading: "Use of service",
      body: "You agree to use JetPakistan only for lawful travel booking purposes and to provide accurate passenger information.",
    },
    {
      id: "terms-2",
      heading: "Bookings and payments",
      body: "Fares are subject to airline rules and availability. Payment must be completed before ticketing where required.",
    },
    {
      id: "terms-3",
      heading: "Support and changes",
      body: "Contact JetPakistan support for help with booking changes. Airline fare rules apply to modifications and cancellations.",
    },
  ],
  seo: {
    title: "Terms of service — JetPakistan",
    description: "JetPakistan terms of service for online flight booking.",
    robots: "index,follow",
  },
};

export const PRIVACY_DOCUMENT_FIXTURE: LegalDocument = {
  source: "fixture",
  title: "Privacy policy",
  effectiveDate: "2026-01-01",
  intro: "This policy explains how JetPakistan collects, uses, and protects your personal information.",
  sections: [
    {
      id: "privacy-1",
      heading: "Information we collect",
      body: "We collect contact details, booking information, and payment references needed to complete your travel requests.",
    },
    {
      id: "privacy-2",
      heading: "How we use data",
      body: "Data is used to process bookings, provide support, and meet legal obligations. We do not sell personal data.",
    },
    {
      id: "privacy-3",
      heading: "Contact",
      body: "For privacy questions, contact JetPakistan using the details on our Contact page.",
    },
  ],
  seo: {
    title: "Privacy policy — JetPakistan",
    description: "JetPakistan privacy policy for online flight booking.",
    robots: "index,follow",
  },
};
