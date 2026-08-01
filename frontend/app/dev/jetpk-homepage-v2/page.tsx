import type { Metadata } from "next";
import { Suspense } from "react";
import { notFound } from "next/navigation";
import { HomepageV2Shell } from "@/features/public-homepage-v2";
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
      <Suspense fallback={null}>
        <HomepageV2Shell />
      </Suspense>
    </PublicThemeV2Root>
  );
}
