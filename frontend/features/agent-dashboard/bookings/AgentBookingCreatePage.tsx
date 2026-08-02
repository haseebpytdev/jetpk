"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { agentApiErrorMessage, fetchAgentBookingCreateEntry } from "../services/agent-dashboard-api";
import {
  AgentDashboardErrorState,
  AgentDashboardShell,
  PermissionDeniedState,
} from "../shell/AgentDashboardShell";
import type { BookingCreateEntry } from "../types";
import type { PublicSession } from "@/types/session";

export function AgentBookingCreatePage({ session }: { session: PublicSession }) {
  const [entry, setEntry] = useState<BookingCreateEntry | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    void (async () => {
      const result = await fetchAgentBookingCreateEntry();
      if (!result.ok) {
        setError(result.status === 403 ? "You do not have permission to create bookings." : agentApiErrorMessage(result));
      } else {
        setEntry(result.data);
      }
      setLoading(false);
    })();
  }, []);

  return (
    <AgentDashboardShell session={session} title="New agency booking">
      {loading ? <p className="text-jp-sm text-jp-muted">Preparing booking mode…</p> : null}
      {error === "You do not have permission to create bookings." ? <PermissionDeniedState message={error} /> : null}
      {error && error !== "You do not have permission to create bookings." ? (
        <AgentDashboardErrorState message={error} />
      ) : null}
      {entry ? (
        <div className="max-w-xl space-y-4" data-testid="agent-booking-create-entry">
          <p className="text-jp-sm text-jp-muted">{entry.message}</p>
          <p className="text-jp-sm text-jp-text">
            Agency: <strong>{entry.agency_name}</strong>
          </p>
          <div className="flex flex-wrap gap-3">
            <Link href={entry.search_url}>
              <PrimaryButton type="button">Search flights</PrimaryButton>
            </Link>
            <Link href="/agent/bookings" className="text-jp-sm text-jp-primary self-center">
              Back to bookings
            </Link>
          </div>
        </div>
      ) : null}
    </AgentDashboardShell>
  );
}
