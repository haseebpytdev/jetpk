import type { CurrencyOption, FooterColumn, NavItem } from "@/types/navigation";

export const primaryNavigation: NavItem[] = [
  {
    type: "dropdown",
    label: "Flights",
    items: [
      { label: "Search Flights", href: "/flights", description: "Compare fares across airlines" },
      { label: "Flight Status", href: "/support/flight-status", description: "Track live departures" },
      { label: "Manage Booking", href: "/manage-booking", description: "Retrieve an existing booking" },
    ],
  },
  { type: "link", label: "Hotels", href: "/hotels" },
  { type: "link", label: "Groups", href: "/groups", badge: "New" },
  { type: "link", label: "Offers", href: "/offers" },
  {
    type: "dropdown",
    label: "Travel Services",
    items: [
      { label: "Visa Assistance", href: "/travel-services/visa", description: "Coming soon" },
      { label: "Travel Insurance", href: "/travel-services/insurance", description: "Coming soon" },
      { label: "Airport Transfers", href: "/travel-services/transfers", description: "Coming soon" },
    ],
  },
  {
    type: "dropdown",
    label: "Support",
    items: [
      { label: "Help Center", href: "/support", description: "Browse help articles" },
      { label: "Contact Us", href: "/contact", description: "Reach our support team" },
      { label: "FAQs", href: "/faq", description: "Common booking questions" },
    ],
  },
];

export const footerColumns: FooterColumn[] = [
  {
    title: "Explore",
    links: [
      { label: "Flights", href: "/flights" },
      { label: "Hotels", href: "/hotels" },
      { label: "Groups", href: "/groups" },
      { label: "Offers", href: "/offers" },
      { label: "Travel Services", href: "/travel-services" },
    ],
  },
  {
    title: "Company",
    links: [
      { label: "About Us", href: "/about-us" },
      { label: "Careers", href: "/careers" },
      { label: "Press", href: "/press" },
      { label: "Investor Relations", href: "/investors" },
      { label: "Sitemap", href: "/sitemap" },
    ],
  },
  {
    title: "Support",
    links: [
      { label: "Help Center", href: "/support" },
      { label: "Contact Us", href: "/contact" },
      { label: "FAQ", href: "/faq" },
      { label: "Manage Booking", href: "/manage-booking" },
      { label: "Baggage Information", href: "/support/baggage" },
      { label: "Flight Status", href: "/support/flight-status" },
    ],
  },
  {
    title: "Legal",
    links: [
      { label: "Terms & Conditions", href: "/terms" },
      { label: "Privacy Policy", href: "/privacy" },
      { label: "Cookie Policy", href: "/legal/cookies" },
      { label: "Refund Policy", href: "/legal/refund" },
    ],
  },
];

export const socialLinks = [
  { label: "Facebook", href: "https://facebook.com" },
  { label: "Instagram", href: "https://instagram.com" },
  { label: "X", href: "https://x.com" },
  { label: "YouTube", href: "https://youtube.com" },
  { label: "LinkedIn", href: "https://linkedin.com" },
];

export const currencyOptions: CurrencyOption[] = [
  { code: "PKR", label: "Pakistani Rupee", symbol: "Rs", flagLabel: "Pakistan" },
  { code: "USD", label: "US Dollar", symbol: "$", flagLabel: "United States" },
  { code: "AED", label: "UAE Dirham", symbol: "AED", flagLabel: "United Arab Emirates" },
  { code: "SAR", label: "Saudi Riyal", symbol: "SAR", flagLabel: "Saudi Arabia" },
];
