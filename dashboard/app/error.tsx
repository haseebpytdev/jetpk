"use client";

import { ErrorState } from "@/components/ui/error-state";
import { stableErrorId } from "@/lib/utils";

export default function GlobalError({
  error,
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  const ref = error.digest ?? stableErrorId("dash");

  return (
    <html lang="en">
      <body className="p-6">
        <ErrorState
          title="Dashboard unavailable"
          message={error.message || "Something went wrong loading the preview dashboard."}
          referenceId={ref}
          onRetry={reset}
        />
      </body>
    </html>
  );
}
