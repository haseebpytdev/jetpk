import type { Metadata } from "next";
import { Suspense } from "react";
import { FlightResultsPage } from "@/features/flight-results";
import { ResultSkeleton } from "@/features/flight-results/components/ResultSkeleton";

export const metadata: Metadata = {
  title: "Flight Results",
  description: "Compare and book flights with JetPakistan.",
};

function ResultsFallback() {
  return (
    <div className="mx-auto max-w-7xl px-4 py-6">
      <ResultSkeleton count={4} />
    </div>
  );
}

export default function FlightResultsRoutePage() {
  return (
    <Suspense fallback={<ResultsFallback />}>
      <FlightResultsPage />
    </Suspense>
  );
}
