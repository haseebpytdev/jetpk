"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { agentApiErrorMessage, fetchAgentAgency } from "../services/agent-dashboard-api";
import {
  AgentDashboardErrorState,
  AgentDashboardShell,
  PermissionDeniedState,
} from "../shell/AgentDashboardShell";
import { pickAgencyDisplayFields } from "../profile/agency-display";
import type { AgentAgencyProfile } from "../types";
import type { PublicSession } from "@/types/session";

export function AgentAgencyPage({ session }: { session: PublicSession }) {
  const [profile, setProfile] = useState<AgentAgencyProfile | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    void (async () => {
      const result = await fetchAgentAgency();
      if (!result.ok) {
        setError(result.status === 403 ? "You do not have permission to view agency details." : agentApiErrorMessage(result));
      } else {
        setProfile(result.data);
      }
      setLoading(false);
    })();
  }, []);

  const details = (profile?.details ?? {}) as Record<string, unknown>;
  const fields = pickAgencyDisplayFields(details);
  const logoUrl = typeof details.logo_url === "string" ? details.logo_url : null;
  const agencyName = String(details.agency_name ?? "Agency");

  return (
    <AgentDashboardShell session={session} title="Agency profile">
      {loading ? <p className="text-jp-sm text-jp-muted">Loading agency profile…</p> : null}
      {error === "You do not have permission to view agency details." ? <PermissionDeniedState message={error} /> : null}
      {error && error !== "You do not have permission to view agency details." ? (
        <AgentDashboardErrorState message={error} />
      ) : null}
      {profile ? (
        <div className="max-w-2xl space-y-5" data-testid="agent-agency-profile">
          <div className="flex flex-wrap items-start gap-4 rounded-jp-lg border border-jp-border bg-jp-surface p-5">
            {logoUrl ? (
              // eslint-disable-next-line @next/next/no-img-element
              <img src={logoUrl} alt="" className="h-16 w-16 rounded-jp-md border border-jp-border object-contain bg-jp-surface-muted" />
            ) : (
              <div className="flex h-16 w-16 items-center justify-center rounded-jp-md border border-dashed border-jp-border bg-jp-surface-muted text-jp-xs text-jp-muted">
                Logo
              </div>
            )}
            <div className="min-w-0 flex-1">
              <h2 className="font-sans text-jp-h3 font-semibold text-jp-text">{agencyName}</h2>
              {profile.wallet_summary ? (
                <p className="mt-1 text-jp-sm text-jp-muted">
                  Wallet: {profile.wallet_summary.currency} {profile.wallet_summary.balance.toLocaleString()}
                </p>
              ) : null}
              {profile.capabilities.can_edit_agency ? (
                <Link href="/agent/profile" className="mt-3 inline-flex">
                  <PrimaryButton type="button">Edit agency profile</PrimaryButton>
                </Link>
              ) : null}
            </div>
          </div>

          <dl className="grid gap-3 sm:grid-cols-2">
            {fields.map((field) => (
              <div key={field.key} className="rounded-jp-md border border-jp-border bg-jp-surface px-3 py-2.5">
                <dt className="text-[0.68rem] font-semibold uppercase tracking-wide text-jp-muted">{field.label}</dt>
                <dd className="mt-1 text-jp-sm font-medium text-jp-text">{field.value}</dd>
              </div>
            ))}
          </dl>
        </div>
      ) : null}
    </AgentDashboardShell>
  );
}
