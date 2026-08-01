import type { CmsPagePayload } from "@/features/cms-theme-v2";
import type { StepperStep, SummaryLineItem } from "@/features/public-theme-v2";

export const LAB_STEPPER_STEPS: StepperStep[] = [
  { id: "passengers", label: "Passengers" },
  { id: "seats", label: "Seats" },
  { id: "review", label: "Review" },
  { id: "payment", label: "Payment" },
  { id: "confirmation", label: "Confirmation" },
];

export const LAB_SUMMARY_ITEMS: SummaryLineItem[] = [
  { label: "Base fare", value: "PKR 85,000" },
  { label: "Taxes and fees", value: "PKR 12,400" },
  { label: "Service fee", value: "PKR 1,200" },
];

export const LAB_CMS_PAGE: CmsPagePayload = {
  title: "Lab CMS demo",
  template: "landing",
  pageKey: "about",
  blocks: [
    {
      type: "hero",
      eyebrow: "CMS block",
      heading: "Structured content preview",
      body: "This hero block is rendered through the isolated V2 CMS renderer.",
      actions: [{ label: "Learn more", href: "/about-us" }],
    },
    {
      type: "richText",
      html: "<p>Rich text with <strong>emphasis</strong> and a <a href=\"/support\">safe link</a>.</p><script>alert(1)</script>",
    },
    {
      type: "stats",
      heading: "Platform highlights",
      items: [
        { value: "50+", label: "Airlines" },
        { value: "24/7", label: "Support" },
        { value: "Secure", label: "Payments" },
      ],
    },
    {
      type: "faq",
      heading: "Common questions",
      items: [
        {
          question: "How does the CMS renderer work?",
          answer: "Blocks map to approved themed components with sanitization at every boundary.",
        },
        {
          question: "What happens to unknown blocks?",
          answer: "They are skipped silently in production and marked in development.",
        },
      ],
    },
    {
      type: "callout",
      tone: "info",
      heading: "Development only",
      body: "Fixture data exists only inside this visual lab.",
      action: { label: "View support", href: "/support" },
    },
    {
      type: "cardGrid",
      heading: "Travel services",
      columns: 3,
      items: [
        { heading: "Flights", body: "Search and book flights.", href: "/#flight-search" },
        { heading: "Groups", body: "Group travel packages.", href: "/groups/search" },
        { heading: "Support", body: "Get help with your booking.", href: "/support" },
      ],
    },
    {
      type: "timeline",
      heading: "How it works",
      items: [
        { marker: "Step 1", heading: "Search", body: "Find flights for your route." },
        { marker: "Step 2", heading: "Book", body: "Complete passenger details securely." },
        { marker: "Step 3", heading: "Travel", body: "Receive confirmation and support." },
      ],
    },
    {
      type: "gallery",
      heading: "Gallery preview",
      columns: 2,
      items: [
        { src: "/images/home/hero-fallback.svg", alt: "Aircraft at sunrise" },
        { src: "https://images.unsplash.com/photo-1436491865332-7a61a109cc05?w=400", alt: "Window seat view" },
      ],
    },
  ],
};
