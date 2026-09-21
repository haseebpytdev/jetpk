import type { Metadata } from "next";
import { Suspense } from "react";
import Link from "next/link";
import { notFound } from "next/navigation";
import { noIndexMetadata } from "@/features/public-content";
import { FlightResultsPage } from "@/features/flight-results";
import { ResultSkeleton } from "@/features/flight-results/components/ResultSkeleton";
import { ExpiredSearchState } from "@/features/flight-results/components/ExpiredSearchState";
import { publicContentFetchUrl } from "@/features/public-content/utils/laravel-api";

type ShortSearchPageProps = {
  params: Promise<{ ref: string }>;
};

export const metadata: Metadata = noIndexMetadata("Flight search — JetPakistan", {
  description: "Your JetPakistan flight search session.",
  path: "/flights/s",
  follow: false,
});

type ShortRefPayload = {
  purpose?: string;
  target_type?: string;
  target_key?: string;
  expired?: boolean;
  message?: string;
};

function ResultsFallback() {
  return (
    <div className="mx-auto max-w-7xl px-4 py-6">
      <ResultSkeleton count={4} />
    </div>
  );
}

function ShortSearchExpired({ message }: { message: string }) {
  return (
    <div className="mx-auto max-w-3xl px-4 py-10">
      <ExpiredSearchState message={message} />
      <p className="mt-4 text-sm text-slate-600">
        <Link href="/" className="font-medium text-jp-primary underline-offset-2 hover:underline">
          Return home
        </Link>{" "}
        to start a new search.
      </p>
    </div>
  );
}

/**
 * True short search URL (§32 / Class B). Resolves opaque ref server-side and renders
 * the same results shell. Browser URL stays `/flights/s/{ref}` — never redirects to
 * `/flights/results?search_id=`. Default search handoff prefers this path.
 */
export default async function FlightShortSearchPage({ params }: ShortSearchPageProps) {
  const { ref } = await params;
  const code = ref.trim();
  if (!/^[A-Za-z0-9]{8,32}$/.test(code)) {
    notFound();
  }

  let payload: ShortRefPayload | null = null;
  let expired = false;
  let expiredMessage = "This search link has expired. Please start a new search.";

  try {
    // SSR must use absolute LARAVEL_URL — relative /laravel is invalid in Node fetch.
    const response = await fetch(
      publicContentFetchUrl(
        `/api/public/content/short-refs/${encodeURIComponent(code)}?purpose=flight_search`,
      ),
      {
        headers: { Accept: "application/json" },
        cache: "no-store",
        signal: AbortSignal.timeout(3_000),
      },
    );
    if (response.status === 410) {
      expired = true;
      try {
        const body = (await response.json()) as ShortRefPayload;
        if (body.message) expiredMessage = body.message;
      } catch {
        /* keep default */
      }
    } else if (!response.ok) {
      notFound();
    } else {
      payload = (await response.json()) as ShortRefPayload;
    }
  } catch {
    notFound();
  }

  if (expired) {
    return <ShortSearchExpired message={expiredMessage} />;
  }

  if (
    payload?.purpose !== "flight_search" ||
    payload.target_type !== "search_id" ||
    !payload.target_key
  ) {
    return <ShortSearchExpired message={expiredMessage} />;
  }

  return (
    <Suspense fallback={<ResultsFallback />}>
      <FlightResultsPage initialSearchId={payload.target_key} shortRef={code} />
    </Suspense>
  );
}
