import type { CurrencyOption, FooterColumn, NavItem } from "@/types/navigation";

export const primaryNavigation: NavItem[] = [
  {
    type: "dropdown",
    label: "Flights",
    items: [
      { label: "Search Flights", href: "/", description: "Compare fares across airlines" },
      { label: "Flight Status", href: "/faq", description: "Common booking and travel questions" },
      { label: "Manage Booking", href: "/lookup-booking", description: "Retrieve an existing booking" },
    ],
  },
  { type: "link", label: "Groups", href: "/groups/search", badge: "New" },
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
      { label: "Flights", href: "/" },
      { label: "Groups", href: "/groups/search" },
      { label: "Manage Booking", href: "/lookup-booking" },
    ],
  },
  {
    title: "Company",
    links: [
      { label: "About Us", href: "/about-us" },
      { label: "Sitemap", href: "/sitemap" },
    ],
  },
  {
    title: "Support",
    links: [
      { label: "Help Center", href: "/support" },
      { label: "Contact Us", href: "/contact" },
      { label: "FAQ", href: "/faq" },
      { label: "Manage Booking", href: "/lookup-booking" },
      { label: "Baggage Information", href: "/faq" },
    ],
  },
  {
    title: "Legal",
    links: [
      { label: "Terms & Conditions", href: "/terms" },
      { label: "Privacy Policy", href: "/privacy" },
    ],
  },
];

export const socialLinks = [
  { label: "Facebook", href: "https://www.facebook.com/jetpakistancom/" },
  { label: "Instagram", href: "https://www.instagram.com/jetpakistanofficial" },
];

export const currencyOptions: CurrencyOption[] = [
  { code: "PKR", label: "Pakistani Rupee", symbol: "Rs", flagLabel: "Pakistan" },
  { code: "USD", label: "US Dollar", symbol: "$", flagLabel: "United States" },
  { code: "AED", label: "UAE Dirham", symbol: "AED", flagLabel: "United Arab Emirates" },
  { code: "SAR", label: "Saudi Riyal", symbol: "SAR", flagLabel: "Saudi Arabia" },
];
