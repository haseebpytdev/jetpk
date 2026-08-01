/**
 * Dev-only homepage literals from the approved mockup / Mock Shell.
 * Used exclusively by the gated /__dev/jetpk-homepage-v2 route.
 * Mark controls with data-review-fixture="true" — not production defaults.
 */

export const HOMEPAGE_V2_BENEFITS = [
  { title: "Best Price Guarantee", description: "We ensure you get the best deals", icon: "◉" },
  { title: "Secure Booking", description: "Your data is safe with us", icon: "♢" },
  { title: "24/7 Customer Support", description: "We are here to help", icon: "◌" },
  { title: "Easy & Fast Booking", description: "Book in just a few steps", icon: "▣" },
] as const;

export const HOMEPAGE_V2_DESTINATIONS = [
  { from: "Lahore", to: "Jeddah", price: "PKR 48,950", airline: "Saudia", imageClass: "image-1" },
  { from: "Islamabad", to: "Dubai", price: "PKR 42,500", airline: "Emirates", imageClass: "image-2" },
  { from: "Karachi", to: "Istanbul", price: "PKR 67,800", airline: "Turkish Airlines", imageClass: "image-3" },
  { from: "Lahore", to: "London", price: "PKR 92,000", airline: "British Airways", imageClass: "image-4" },
  { from: "Islamabad", to: "Riyadh", price: "PKR 46,300", airline: "Saudia", imageClass: "image-5" },
] as const;

export const HOMEPAGE_V2_OFFERS = [
  { title: "Summer Saver", discount: "20% OFF", caption: "On International Flights", variant: 1 },
  { title: "Weekend Getaway", discount: "15% OFF", caption: "On Selected Routes", variant: 2 },
  { title: "Family Travel Deal", discount: "10% OFF", caption: "For Families", variant: 3 },
] as const;

export const HOMEPAGE_V2_WHY = [
  { title: "Best Prices", description: "We match the best fares for you.", icon: "◉" },
  { title: "Trusted & Secure", description: "Your safety and privacy are our priority.", icon: "♢" },
  { title: "Flexible Options", description: "Choose what works best for you.", icon: "⇄" },
  { title: "Generous Baggage", description: "More allowance, more peace of mind.", icon: "▣" },
  { title: "On-Time Performance", description: "Reliable flights, on your time.", icon: "◌" },
] as const;

export const HOMEPAGE_V2_INSPIRATION = [
  { category: "TRAVEL GUIDE", title: "Top 10 Places to Visit in Turkey", meta: "May 20, 2026 · 5 min read", imageClass: "image-1" },
  { category: "TRAVEL TIPS", title: "How to Find the Cheapest Flight Tickets", meta: "May 18, 2026 · 5 min read", imageClass: "image-2" },
  { category: "DESTINATION", title: "Explore the Beauty of Northern Pakistan", meta: "May 15, 2026 · 5 min read", imageClass: "image-3" },
  { category: "NEWS", title: "JetPakistan Expands Network with New Routes", meta: "May 12, 2026 · 5 min read", imageClass: "image-4" },
] as const;

export const HOMEPAGE_V2_NAV = [
  { label: "Flights", dropdown: true },
  { label: "Hotels", dropdown: false },
  { label: "Groups", dropdown: false, badge: "New" },
  { label: "Offers", dropdown: false },
  { label: "Travel Services", dropdown: true },
  { label: "Support", dropdown: true },
] as const;

export const HOMEPAGE_V2_FOOTER_COLUMNS = [
  { title: "Explore", links: ["Flights", "Hotels", "Groups", "Offers"] },
  { title: "Company", links: ["About Us", "Careers", "Press", "Blog"] },
  { title: "Support", links: ["Help Center", "Contact Us", "Manage Booking", "FAQs"] },
  { title: "Legal", links: ["Terms & Conditions", "Privacy Policy", "Cookie Policy", "Sitemap"] },
] as const;

export const HOMEPAGE_V2_SEARCH = {
  tabs: ["One Way", "Round Trip", "Multi-City"] as const,
  origin: { code: "LHE", city: "Lahore", airport: "Allama Iqbal Intl." },
  destination: { code: "JED", city: "Jeddah", airport: "King Abdulaziz Intl." },
  departure: { date: "20 Jun, 2026", day: "Saturday" },
  passengers: { count: "1 Passenger", cabin: "Economy" },
} as const;
