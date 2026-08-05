"use client";

import { useState } from "react";
import { useDashboardPortal } from "@/lib/portal-context";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import {
  assignSupportTicket,
  forwardSupportTicket,
  replySupportTicket,
  updateSupportTicketStatus,
} from "@/services/operational-api";
import type { SupportTicketRecord } from "@/mocks/support-fixtures";

export function SupportOperationalWorkspace({ tickets }: { tickets: SupportTicketRecord[] }) {
  const portal = useDashboardPortal();
  const isLive = useDashboardLiveMode();
  const [rows, setRows] = useState(tickets);
  const [busyKey, setBusyKey] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [replyBody, setReplyBody] = useState<Record<string, string>>({});

  if (!isLive) {
    return (
      <p className="text-sm text-jp-muted" data-testid="support-ops-preview">
        Support operational actions are available in live dashboard mode only.
      </p>
    );
  }

  async function run(key: string, action: () => Promise<{ ok: boolean; message?: string }>, onOk?: () => void) {
    setBusyKey(key);
    setError(null);
    const result = await action();
    setBusyKey(null);
    if (!result.ok) {
      setError(result.message ?? "Request failed");
      return;
    }
    onOk?.();
  }

  return (
    <div className="space-y-4" data-testid="support-operational-workspace">
      {error ? <p className="text-sm text-red-600">{error}</p> : null}
      <ul className="space-y-3">
        {rows.map((ticket) => (
          <li key={ticket.id} className="rounded-xl border border-jp-border p-4 text-sm">
            <p className="font-medium">{ticket.subject}</p>
            <p className="text-jp-muted">Ticket {ticket.id} · Status: {ticket.status}</p>
            <div className="mt-3 flex flex-wrap gap-2">
              <button
                type="button"
                className="min-h-11 rounded-xl border border-jp-border px-3 py-2 disabled:opacity-60"
                disabled={busyKey !== null}
                data-testid={`support-resolve-${ticket.id}`}
                onClick={() =>
                  run(
                    `resolve-${ticket.id}`,
                    () => updateSupportTicketStatus(portal, ticket.id, "resolved"),
                    () =>
                      setRows((current) =>
                        current.map((row) => (row.id === ticket.id ? { ...row, status: "resolved" } : row)),
                      ),
                  )
                }
              >
                Resolve
              </button>
              <button
                type="button"
                className="min-h-11 rounded-xl border border-jp-border px-3 py-2 disabled:opacity-60"
                disabled={busyKey !== null}
                data-testid={`support-forward-${ticket.id}`}
                onClick={() => run(`forward-${ticket.id}`, () => forwardSupportTicket(ticket.id, null))}
              >
                Clear forward
              </button>
              <button
                type="button"
                className="min-h-11 rounded-xl border border-jp-border px-3 py-2 disabled:opacity-60"
                disabled={busyKey !== null}
                data-testid={`support-assign-${ticket.id}`}
                onClick={() =>
                  run(`assign-${ticket.id}`, () => assignSupportTicket(ticket.id, null), () =>
                    setRows((current) =>
                      current.map((row) => (row.id === ticket.id ? { ...row, assignedTo: "unassigned" } : row)),
                    ),
                  )
                }
              >
                Clear assignment
              </button>
            </div>
            <div className="mt-3 space-y-2">
              <textarea
                className="w-full rounded-lg border border-jp-border p-2 text-sm"
                placeholder="Internal reply"
                value={replyBody[ticket.id] ?? ""}
                onChange={(e) => setReplyBody((current) => ({ ...current, [ticket.id]: e.target.value }))}
                data-testid={`support-reply-input-${ticket.id}`}
              />
              <button
                type="button"
                className="min-h-11 rounded-xl bg-jp-accent px-3 py-2 text-white disabled:opacity-60"
                disabled={busyKey !== null}
                data-testid={`support-reply-${ticket.id}`}
                onClick={() => {
                  const body = replyBody[ticket.id]?.trim();
                  if (!body) {
                    setError("Reply body is required.");
                    return;
                  }
                  run(`reply-${ticket.id}`, () => replySupportTicket(portal, ticket.id, body, "internal"));
                }}
              >
                Send internal reply
              </button>
            </div>
          </li>
        ))}
      </ul>
    </div>
  );
}
