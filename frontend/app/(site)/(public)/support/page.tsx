import type { Metadata } from "next";
import { SupportClientPage } from "./SupportClientPage";

/**
 * Soft-nav: static metadata + client CMS body — Flight URL-commit never waits on Laravel.
 */
export const metadata: Metadata = {
  title: "Support | JetPakistan",
  description: "Get help with bookings, payments, and travel support from JetPakistan.",
  robots: { index: true, follow: true },
  alternates: { canonical: "/support" },
};

export default function SupportPage() {
  return <SupportClientPage />;
}
