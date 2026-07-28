"use client";

import { PublicContentErrorState } from "@/features/public-content";
import { useEffect } from "react";

type ErrorPageProps = {
  error: Error & { digest?: string };
  reset: () => void;
};

export default function ErrorPage({ error, reset }: ErrorPageProps) {
  useEffect(() => {
    console.error(error);
  }, [error]);

  return (
    <div className="flex min-h-[50vh] items-center justify-center px-4 py-jp-4xl">
      <PublicContentErrorState
        title="Something went wrong"
        message="We could not load this page right now. Please try again or return home."
        onRetry={reset}
      />
    </div>
  );
}
