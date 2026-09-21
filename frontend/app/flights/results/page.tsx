import type { Metadata } from "next";
import { Suspense } from "react";
import { FlightResultsPage } from "@/features/flight-results";
import { ResultSkeleton } from "@/features/flight-results/components/ResultSkeleton";
import { noIndexMetadata } from "@/features/public-content";

/** Legacy long results URL — compatibility only; not indexable; short `/flights/s/{ref}` is preferred. */
export const metadata: Metadata = noIndexMetadata("Flight Results — JetPakistan", {
  description: "Compare and book flights with JetPakistan.",
  path: "/flights/results",
  follow: false,
});

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
