import type { FaqPageContent } from "../types";

/** Fixture boundary — mirrors Laravel bootstrap FAQ when CMS is unpublished. */
export const FAQ_PAGE_FIXTURE: FaqPageContent = {
  source: "fixture",
  hero: {
    kicker: "Help centre",
    title: "Frequently asked questions",
    description: "Answers to common questions about booking, payments, and managing your trip.",
  },
  categories: [
    {
      id: "faq-cat-booking",
      title: "Booking",
      items: [
        {
          id: "faq-q-1",
          categoryId: "faq-cat-booking",
          question: "How do I book a flight on JetPakistan?",
          answer:
            "Search your route on the homepage, select dates and travellers, then complete checkout with your passenger details.",
        },
        {
          id: "faq-q-2",
          categoryId: "faq-cat-booking",
          question: "Can I book domestic and international flights?",
          answer:
            "Yes. JetPakistan supports both domestic Pakistan routes and international destinations from major Pakistani cities.",
        },
      ],
    },
    {
      id: "faq-cat-payments",
      title: "Payments",
      items: [
        {
          id: "faq-q-3",
          categoryId: "faq-cat-payments",
          question: "What payment methods are supported?",
          answer:
            "Available payment options are shown during checkout. Contact support if you need help with a payment confirmation.",
        },
      ],
    },
    {
      id: "faq-cat-after",
      title: "After booking",
      items: [
        {
          id: "faq-q-4",
          categoryId: "faq-cat-after",
          question: "How do I get help after booking?",
          answer:
            "Contact us by phone, WhatsApp, or email with your booking reference. You can also use Manage booking on the website.",
        },
      ],
    },
    {
      id: "faq-cat-account",
      title: "Account and login",
      items: [
        {
          id: "faq-q-5",
          categoryId: "faq-cat-account",
          question: "How do I access my account?",
          answer: "Sign in from the header menu. Registration is available for new customers who want saved traveller profiles.",
        },
      ],
    },
  ],
  cta: { label: "Contact support", href: "/support" },
  seo: {
    title: "FAQ — JetPakistan",
    description: "JetPakistan help centre — booking, payments, and trip management FAQs.",
    robots: "index,follow",
  },
};
