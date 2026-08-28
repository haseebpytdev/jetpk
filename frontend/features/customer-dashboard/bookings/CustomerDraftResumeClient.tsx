"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { ensureLaravelCsrfToken } from "@/lib/api";
import { laravelApiPath } from "@/services/flight-search";
import { isAllowedBookingNextUrl, resolveBookingNextUrl } from "@/features/standard-booking/utils/allowlist";

type ResumePageProps = {
  reference: string;
};

/**
 * Server-backed Draft resume: binds THIS booking into checkout session, then navigates to travelers.
 */
export function CustomerDraftResumeClient({ reference }: ResumePageProps) {
  const router = useRouter();
  const [message, setMessage] = useState("Resuming your draft booking…");

  useEffect(() => {
    let cancelled = false;

    async function run() {
      try {
        await ensureLaravelCsrfToken(true);
        const response = await fetch(
          laravelApiPath(`/customer/bookings/${encodeURIComponent(reference)}/resume?format=json`),
          {
            method: "GET",
            credentials: "include",
            headers: {
              Accept: "application/json",
              "X-Requested-With": "XMLHttpRequest",
            },
            cache: "no-store",
          },
        );
        const body = (await response.json().catch(() => null)) as {
          ok?: boolean;
          next_url?: string;
          message?: string;
          booking_id?: number;
        } | null;

        if (cancelled) return;

        if (!response.ok || !body?.ok || !body.next_url) {
          setMessage(body?.message || "Unable to resume this draft. Return to bookings and try again.");
          return;
        }

        const next = resolveBookingNextUrl(body.next_url);
        if (!next || !isAllowedBookingNextUrl(next)) {
          // Passengers path is the only safe resume target from this flow.
          if (body.next_url === "/booking/passengers" || body.next_url.endsWith("/booking/passengers")) {
            window.location.assign("/booking/passengers");
            return;
          }
          setMessage("Invalid resume destination.");
          return;
        }

        window.location.assign(next);
      } catch {
        if (!cancelled) setMessage("Network error while resuming draft.");
      }
    }

    void run();
    return () => {
      cancelled = true;
    };
  }, [reference, router]);

  return (
    <div className="mx-auto max-w-lg px-4 py-16 text-center" data-testid="customer-draft-resume">
      <p className="text-jp-sm text-jp-muted">{message}</p>
      <a href={`/customer/bookings/${encodeURIComponent(reference)}`} className="mt-4 inline-block text-jp-sm font-semibold text-jp-primary">
        Back to booking
      </a>
    </div>
  );
}
