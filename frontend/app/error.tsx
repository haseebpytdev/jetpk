"use client";

import { PublicContentErrorState } from "@/features/public-content";
import { useEffect, useRef } from "react";

type ErrorPageProps = {
  error: Error & { digest?: string };
  reset: () => void;
};

export default function ErrorPage({ error, reset }: ErrorPageProps) {
  const retryCountRef = useRef(0);

  useEffect(() => {
    console.error("[jetpk-public-error]", {
      name: error?.name,
      message: error?.message,
      digest: error?.digest,
    });
  }, [error]);

  const handleRetry = () => {
    retryCountRef.current += 1;
    // First retry: attempt React reset. Repeated failures: hard-reload clean route state.
    if (retryCountRef.current <= 1) {
      reset();
      return;
    }
    if (typeof window !== "undefined") {
      window.location.assign(window.location.pathname + window.location.search);
    }
  };

  return (
    <div className="flex min-h-[50vh] items-center justify-center px-4 py-jp-4xl">
      <PublicContentErrorState
        title="Something went wrong"
        message="We could not load this page right now. Please try again or return home."
        onRetry={handleRetry}
      />
    </div>
  );
}
