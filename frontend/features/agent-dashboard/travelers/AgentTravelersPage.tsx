"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import {
  agentApiErrorMessage,
  deleteAgentTraveler,
  fetchAgentTravelers,
} from "../services/agent-dashboard-api";
import {
  AgentDashboardErrorState,
  AgentDashboardShell,
  AgentEmptyState,
  PermissionDeniedState,
} from "../shell/AgentDashboardShell";
import type { AgentSavedTraveler } from "../types";
import type { PublicSession } from "@/types/session";

export function AgentTravelersPage({ session }: { session: PublicSession }) {
  const [travelers, setTravelers] = useState<AgentSavedTraveler[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [denied, setDenied] = useState(false);
  const [loading, setLoading] = useState(true);

  const load = async () => {
    setLoading(true);
    setError(null);
    setDenied(false);
    const result = await fetchAgentTravelers();
    if (!result.ok) {
      if (result.status === 403) {
        setDenied(true);
      } else {
        setError(agentApiErrorMessage(result));
      }
      setTravelers([]);
    } else {
      setTravelers(result.data.travelers);
    }
    setLoading(false);
  };

  useEffect(() => {
    void load();
  }, []);

  const handleDelete = async (travelerId: number) => {
    if (!window.confirm("Remove this saved traveler?")) return;
    const result = await deleteAgentTraveler(travelerId);
    if (!result.ok) setError(agentApiErrorMessage(result));
    else await load();
  };

  if (denied) {
    return (
      <AgentDashboardShell session={session} title="Travelers">
        <PermissionDeniedState message="You do not have permission to manage travelers." />
      </AgentDashboardShell>
    );
  }

  return (
    <AgentDashboardShell session={session} title="Travelers">
      <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
        <Link
          href="/agent/travelers/new"
          className="inline-flex items-center justify-center rounded-jp-button bg-jp-primary px-4 py-2 text-jp-sm font-semibold text-white focus-visible:shadow-jp-focus"
        >
          Add traveler
        </Link>
      </div>

      {loading ? <p className="text-jp-sm text-jp-muted">Loading travelers…</p> : null}
      {error ? <AgentDashboardErrorState message={error} onRetry={load} /> : null}

      {!loading && !error && travelers.length === 0 ? (
        <AgentEmptyState
          title="No saved travelers"
          description="Save traveler profiles for faster agency bookings."
          action={
            <Link
              href="/agent/travelers/new"
              className="inline-flex items-center justify-center rounded-jp-button bg-jp-primary px-4 py-2 text-jp-sm font-semibold text-white"
            >
              Add traveler
            </Link>
          }
        />
      ) : null}

      <div className="space-y-3" data-testid="agent-travelers-list">
        {travelers.map((traveler) => (
          <article key={traveler.id} className="rounded-jp-lg border border-jp-border bg-jp-surface p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
              <div>
                <p className="font-semibold text-jp-text">
                  {traveler.title} {traveler.first_name} {traveler.last_name}
                </p>
                <p className="text-jp-sm text-jp-muted">
                  {traveler.nationality} · {traveler.document_type}
                  {traveler.document_number_masked ? ` · ${traveler.document_number_masked}` : ""}
                  {traveler.is_default ? " · Default" : ""}
                </p>
              </div>
              <div className="flex gap-2">
                {traveler.id ? (
                  <Link href={`/agent/travelers/${traveler.id}/edit`} className="text-jp-sm text-jp-primary">
                    Edit
                  </Link>
                ) : null}
                {traveler.id ? (
                  <button
                    type="button"
                    className="text-jp-sm text-red-700"
                    onClick={() => void handleDelete(traveler.id!)}
                  >
                    Delete
                  </button>
                ) : null}
              </div>
            </div>
          </article>
        ))}
      </div>
    </AgentDashboardShell>
  );
}
