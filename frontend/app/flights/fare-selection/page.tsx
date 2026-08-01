import type { Metadata } from "next";
import { Suspense } from "react";
import { FareSelectionPage } from "@/features/flight-details/components/FareSelectionPage";
import { ResultSkeleton } from "@/features/flight-results/components/ResultSkeleton";

export const metadata: Metadata = {
  title: "Choose Your Fare",
  description: "Select a fare family and continue to passenger details.",
  robots: { index: false, follow: false },
};

function FareSelectionFallback() {
  return (
    <div className="mx-auto max-w-jp-booking px-jp-xl py-jp-2xl">
      <ResultSkeleton count={3} />
    </div>
  );
}

export default function FareSelectionRoutePage() {
  return (
    <Suspense fallback={<FareSelectionFallback />}>
      <FareSelectionPage />
    </Suspense>
  );
}
