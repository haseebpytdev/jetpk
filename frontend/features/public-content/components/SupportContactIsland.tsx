"use client";

import dynamic from "next/dynamic";

const ContactForm = dynamic(
  () => import("./ContactForm").then((mod) => mod.ContactForm),
  {
    ssr: false,
    loading: () => (
      <p className="text-jp-sm text-jp-muted">The support form will appear in a moment.</p>
    ),
  },
);

const DEFAULT_CATEGORIES = [
  { value: "booking", label: "Booking" },
  { value: "payment", label: "Payment" },
  { value: "technical", label: "Technical" },
  { value: "other", label: "Other" },
];

export function SupportContactIsland() {
  return (
    <ContactForm formType="support" showBookingReference showCategory categories={DEFAULT_CATEGORIES} />
  );
}
