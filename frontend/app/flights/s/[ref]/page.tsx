import type { Metadata } from "next";
import { redirect, notFound } from "next/navigation";
import { noIndexMetadata } from "@/features/public-content";
import { laravelApiPath } from "@/services/flight-search";

type ShortSearchPageProps = {
  params: Promise<{ ref: string }>;
};

export const metadata: Metadata = noIndexMetadata("Flight search — JetPakistan", {
  description: "Resolving your JetPakistan flight search session.",
  path: "/flights/s",
  follow: false,
});

type ShortRefPayload = {
  purpose?: string;
  target_type?: string;
  target_key?: string;
  expired?: boolean;
};

/**
 * Additive short search URL (§32). Resolves opaque ref → existing results shell.
 * Legacy `/flights/results?search_id=` remains valid. Soft-nav default URL cutover
 * is deferred until same-SHA recert.
 */
export default async function FlightShortSearchPage({ params }: ShortSearchPageProps) {
  const { ref } = await params;
  const code = ref.trim();
  if (!/^[A-Za-z0-9]{8,32}$/.test(code)) {
    notFound();
  }

  let payload: ShortRefPayload | null = null;
  try {
    const response = await fetch(laravelApiPath(`/api/public/content/short-refs/${encodeURIComponent(code)}`), {
      headers: { Accept: "application/json" },
      cache: "no-store",
      signal: AbortSignal.timeout(3_000),
    });
    if (response.status === 410) {
      redirect("/flights/results?expired=1");
    }
    if (!response.ok) {
      notFound();
    }
    payload = (await response.json()) as ShortRefPayload;
  } catch {
    notFound();
  }

  if (
    payload?.purpose !== "flight_search" ||
    payload.target_type !== "search_id" ||
    !payload.target_key
  ) {
    notFound();
  }

  redirect(`/flights/results?search_id=${encodeURIComponent(payload.target_key)}`);
}
