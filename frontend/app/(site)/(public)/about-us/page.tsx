import type { Metadata } from "next";
import { AboutClientPage } from "./AboutClientPage";

/**
 * Soft-nav: static metadata + client CMS body — Flight URL-commit never waits on Laravel.
 */
export const metadata: Metadata = {
  title: "About us | JetPakistan",
  description: "Learn about JetPakistan — flights, group ticketing, and travel services across Pakistan.",
  robots: { index: true, follow: true },
  alternates: { canonical: "/about-us" },
};

export default function AboutUsPage() {
  return <AboutClientPage />;
}
