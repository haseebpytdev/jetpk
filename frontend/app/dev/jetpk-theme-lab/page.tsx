import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { PublicThemeV2Root } from "@/features/public-theme-v2";
import { isThemeLabAllowed } from "@/features/public-theme-v2/lab/is-theme-lab-allowed";
import { ThemeLabContent } from "@/features/public-theme-v2/lab/ThemeLabContent";

export const metadata: Metadata = {
  title: "JetPakistan Theme Lab",
  robots: {
    index: false,
    follow: false,
  },
};

export default function JetPkThemeLabPage() {
  if (!isThemeLabAllowed()) {
    notFound();
  }

  return (
    <PublicThemeV2Root initialTheme="light">
      <ThemeLabContent />
    </PublicThemeV2Root>
  );
}
