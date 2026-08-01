import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { HomepageV2Composition } from "@/features/public-homepage-v2";
import { PublicThemeV2Root } from "@/features/public-theme-v2";
import { isThemeLabAllowed } from "@/features/public-theme-v2/lab/is-theme-lab-allowed";

export const metadata: Metadata = {
  title: "JetPakistan Homepage V2 Review",
  robots: {
    index: false,
    follow: false,
  },
};

export default function JetPkHomepageV2Page() {
  if (!isThemeLabAllowed()) {
    notFound();
  }

  return (
    <PublicThemeV2Root initialTheme="light">
      <HomepageV2Composition />
    </PublicThemeV2Root>
  );
}
