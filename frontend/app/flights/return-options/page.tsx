import { Suspense } from "react";
import { ReturnOptionsPage } from "@/features/flight-results/components/ReturnOptionsPage";
import { ResultSkeleton } from "@/features/flight-results/components/ResultSkeleton";

export default function ReturnOptionsRoutePage() {
  return (
    <Suspense
      fallback={
        <div className="mx-auto max-w-5xl px-4 py-6">
          <ResultSkeleton count={3} />
        </div>
      }
    >
      <ReturnOptionsPage />
    </Suspense>
  );
}
