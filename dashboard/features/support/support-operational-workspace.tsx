"use client";

import { useEffect, useState } from "react";
import { useDashboardPortal } from "@/lib/portal-context";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import { laravelRequest } from "@/lib/api/laravel-action-client";
import {
  supportTicketShowPath,
  supportTicketsIndexPath,
} from "@/lib/api/portal-paths";
import { emptyListDescription } from "@/lib/empty-list-copy";
import {
  assignSupportTicket,
  forwardSupportTicket,
  replySupportTicket,
  updateSupportTicketStatus,
} from "@/services/operational-api";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";

type TicketRow = {
  id: string;
  subject: string;
  status: string;
  assigned_to?: string | null;
  assigned_to_user_id?: string | null;
  forwarded_to_agent_id?: string | null;
  forwarded_to?: string | null;
  last_reply_at?: string | null;
};

type TicketMessage = {
  id: string;
  body: string;
  visibility: string;
  author?: string | null;
  created_at?: string | null;
};

type TicketDetail = TicketRow & {
  messages?: TicketMessage[];
  booking_reference?: string | null;
};

type CatalogOption = { id: string; name?: string | null; email?: string | null; code?: string | null };

type SupportIndexPayload = {
  ok?: boolean;
  tickets?: TicketRow[];
  meta?: {
    page?: number;
    pageCount?: number;
    pageSize?: number;
    total?: number;
    current_page?: number;
    last_page?: number;
    per_page?: number;
  };
};

type SupportShowPayload = {
  ok?: boolean;
  ticket?: TicketDetail;
  assignees?: CatalogOption[];
  agents?: CatalogOption[];
  statuses?: string[];
};

const DEFAULT_PAGE_SIZE = 10;

export function SupportOperationalWorkspace() {
  const portal = useDashboardPortal();
  const isLive = useDashboardLiveMode();
  const [rows, setRows] = useState<TicketRow[]>([]);
  const [page, setPage] = useState(1);
  const [pageCount, setPageCount] = useState(1);
  const [total, setTotal] = useState(0);
  const [pageSize, setPageSize] = useState(DEFAULT_PAGE_SIZE);
  const [search, setSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState("");
  const [loading, setLoading] = useState(false);
  const [busyKey, setBusyKey] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [detail, setDetail] = useState<TicketDetail | null>(null);
  const [assignees, setAssignees] = useState<CatalogOption[]>([]);
  const [agents, setAgents] = useState<CatalogOption[]>([]);
  const [statuses, setStatuses] = useState<string[]>([]);
  const [assignTo, setAssignTo] = useState("");
  const [forwardTo, setForwardTo] = useState("");
  const [statusValue, setStatusValue] = useState("");
  const [replyBody, setReplyBody] = useState("");
  const [replyVisibility, setReplyVisibility] = useState<"internal" | "customer_visible">("internal");

  const refresh = async (nextPage = page) => {
    setLoading(true);
    setError(null);
    const params = new URLSearchParams();
    params.set("page", String(nextPage));
    params.set("pageSize", String(DEFAULT_PAGE_SIZE));
    if (statusFilter) params.set("status", statusFilter);
    if (search.trim()) params.set("queue", search.trim());
    const result = await laravelRequest<SupportIndexPayload>(
      supportTicketsIndexPath(params.toString()),
      {
        method: "GET",
        headers: { Accept: "application/json" },
        retryCsrfOnce: false,
      },
    );
    setLoading(false);
    if (!result.ok) {
      setError(result.message ?? "Could not load support tickets.");
      setRows([]);
      return;
    }
    const meta = result.data.meta ?? {};
    setRows(
      (result.data.tickets ?? []).map((ticket) => ({
        id: String(ticket.id ?? ""),
        subject: String(ticket.subject ?? "Support ticket"),
        status: String(ticket.status ?? "open"),
        assigned_to: ticket.assigned_to ?? null,
        assigned_to_user_id: ticket.assigned_to_user_id ?? null,
        forwarded_to_agent_id: ticket.forwarded_to_agent_id ?? null,
        forwarded_to: ticket.forwarded_to ?? null,
        last_reply_at: ticket.last_reply_at ?? null,
      })).filter((ticket) => ticket.id !== ""),
    );
    setPage(meta.page ?? meta.current_page ?? nextPage);
    setPageCount(meta.pageCount ?? meta.last_page ?? 1);
    setPageSize(meta.pageSize ?? meta.per_page ?? DEFAULT_PAGE_SIZE);
    setTotal(meta.total ?? 0);
  };

  const loadDetail = async (ticketId: string) => {
    setSelectedId(ticketId);
    setError(null);
    const result = await laravelRequest<SupportShowPayload>(supportTicketShowPath(ticketId), {
      method: "GET",
      headers: { Accept: "application/json" },
      retryCsrfOnce: false,
    });
    if (!result.ok) {
      setError(result.message ?? "Could not load ticket detail.");
      setDetail(null);
      return;
    }
    setDetail(result.data.ticket ?? null);
    setAssignees(result.data.assignees ?? []);
    setAgents(result.data.agents ?? []);
    setStatuses(result.data.statuses ?? []);
    setAssignTo(result.data.ticket?.assigned_to_user_id ?? "");
    setForwardTo(result.data.ticket?.forwarded_to_agent_id ?? "");
    setStatusValue(result.data.ticket?.status ?? "");
  };

  useEffect(() => {
    if (!isLive) return;
    void refresh(1);
  }, [isLive]);

  if (!isLive) {
    return (
      <p className="text-sm text-jp-muted" data-testid="support-ops-preview">
        Support operational actions are available in live dashboard mode only.
      </p>
    );
  }

  async function run(key: string, action: () => Promise<{ ok: boolean; message?: string }>) {
    setBusyKey(key);
    setError(null);
    const result = await action();
    setBusyKey(null);
    if (!result.ok) {
      setError(result.message ?? "Request failed");
      return false;
    }
    await refresh(page);
    if (selectedId) await loadDetail(selectedId);
    return true;
  }

  return (
    <div className="space-y-4" data-testid="support-operational-workspace">
      <div className="flex flex-wrap items-end gap-2">
        <label className="text-sm">
          <span className="text-xs text-jp-muted">Search / queue</span>
          <Input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="mt-1 max-w-xs"
            data-testid="support-search"
          />
        </label>
        <label className="text-sm">
          <span className="text-xs text-jp-muted">Status</span>
          <select
            className="mt-1 block rounded-lg border border-jp-border px-3 py-2 text-sm"
            value={statusFilter}
            onChange={(e) => setStatusFilter(e.target.value)}
            data-testid="support-status-filter"
          >
            <option value="">All</option>
            <option value="open">open</option>
            <option value="pending">pending</option>
            <option value="resolved">resolved</option>
            <option value="closed">closed</option>
          </select>
        </label>
        <Button
          type="button"
          size="sm"
          onClick={() => void refresh(1)}
          disabled={loading || busyKey !== null}
          data-testid="support-refresh"
        >
          {loading ? "Loading…" : "Search"}
        </Button>
      </div>
      <p className="text-xs text-jp-muted" data-testid="support-pagination-meta">
        Page {page} of {pageCount} · {total} tickets · {pageSize} per page
      </p>
      {error ? (
        <p className="text-sm text-red-600" role="alert" data-testid="support-error">
          {error}
        </p>
      ) : null}
      {!loading && rows.length === 0 && !error ? (
        <p className="text-sm text-jp-muted">{emptyListDescription(true, "Support tickets")}</p>
      ) : null}
      <div className="overflow-x-auto rounded-xl border border-jp-border">
        <table className="min-w-full text-left text-sm" data-testid="support-ticket-table">
          <thead>
            <tr className="border-b text-jp-muted">
              <th className="px-3 py-2">ID</th>
              <th className="px-3 py-2">Subject</th>
              <th className="px-3 py-2">Status</th>
              <th className="px-3 py-2">Assigned</th>
              <th className="px-3 py-2" />
            </tr>
          </thead>
          <tbody>
            {rows.map((ticket) => (
              <tr key={ticket.id} className="border-b last:border-0">
                <td className="px-3 py-2 font-mono text-xs">{ticket.id}</td>
                <td className="px-3 py-2">{ticket.subject}</td>
                <td className="px-3 py-2">{ticket.status}</td>
                <td className="px-3 py-2">{ticket.assigned_to ?? "—"}</td>
                <td className="px-3 py-2 text-right">
                  <Button
                    type="button"
                    size="sm"
                    variant={selectedId === ticket.id ? "primary" : "secondary"}
                    onClick={() => void loadDetail(ticket.id)}
                    data-testid={`support-open-${ticket.id}`}
                  >
                    Open
                  </Button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <div className="flex flex-wrap gap-2" data-testid="support-pagination">
        <Button
          type="button"
          size="sm"
          variant="secondary"
          disabled={page <= 1 || loading}
          onClick={() => void refresh(page - 1)}
        >
          Previous
        </Button>
        <Button
          type="button"
          size="sm"
          variant="secondary"
          disabled={page >= pageCount || loading}
          onClick={() => void refresh(page + 1)}
        >
          Next
        </Button>
      </div>

      {detail ? (
        <section className="space-y-4 rounded-xl border border-jp-border p-4" data-testid="support-detail">
          <div>
            <h2 className="text-base font-semibold">{detail.subject}</h2>
            <p className="text-xs text-jp-muted">
              Ticket {detail.id}
              {detail.booking_reference ? ` · Booking ${detail.booking_reference}` : ""}
              {detail.forwarded_to ? ` · Forwarded: ${detail.forwarded_to}` : ""}
            </p>
          </div>

          <div className="space-y-2" data-testid="support-conversation">
            <h3 className="text-sm font-semibold">Conversation</h3>
            {(detail.messages ?? []).length === 0 ? (
              <p className="text-sm text-jp-muted">No messages yet.</p>
            ) : (
              <ul className="space-y-2">
                {(detail.messages ?? []).map((message) => (
                  <li key={message.id} className="rounded-lg bg-gray-50 p-3 text-sm">
                    <p className="text-xs text-jp-muted">
                      {message.author ?? "System"} · {message.visibility}
                      {message.created_at ? ` · ${message.created_at}` : ""}
                    </p>
                    <p className="mt-1 whitespace-pre-wrap">{message.body}</p>
                  </li>
                ))}
              </ul>
            )}
          </div>

          <div className="grid gap-3 sm:grid-cols-3">
            <label className="text-sm">
              <span className="text-xs text-jp-muted">Assign to staff</span>
              <select
                className="mt-1 w-full rounded-lg border border-jp-border px-3 py-2 text-sm"
                value={assignTo}
                onChange={(e) => setAssignTo(e.target.value)}
                data-testid="support-assign-select"
              >
                <option value="">Unassigned</option>
                {assignees.map((option) => (
                  <option key={option.id} value={option.id}>
                    {option.name ?? option.email ?? option.id}
                  </option>
                ))}
              </select>
            </label>
            <label className="text-sm">
              <span className="text-xs text-jp-muted">Forward to agent</span>
              <select
                className="mt-1 w-full rounded-lg border border-jp-border px-3 py-2 text-sm"
                value={forwardTo}
                onChange={(e) => setForwardTo(e.target.value)}
                data-testid="support-forward-select"
              >
                <option value="">No forward</option>
                {agents.map((option) => (
                  <option key={option.id} value={option.id}>
                    {option.code ?? ""} {option.name ? `· ${option.name}` : ""}
                  </option>
                ))}
              </select>
            </label>
            <label className="text-sm">
              <span className="text-xs text-jp-muted">Status</span>
              <select
                className="mt-1 w-full rounded-lg border border-jp-border px-3 py-2 text-sm"
                value={statusValue}
                onChange={(e) => setStatusValue(e.target.value)}
                data-testid="support-status-select"
              >
                {(statuses.length ? statuses : [detail.status]).map((status) => (
                  <option key={status} value={status}>
                    {status}
                  </option>
                ))}
              </select>
            </label>
          </div>

          <div className="flex flex-wrap gap-2">
            <Button
              type="button"
              size="sm"
              disabled={busyKey !== null}
              data-testid="support-assign-save"
              onClick={() =>
                void run(`assign-${detail.id}`, () =>
                  assignSupportTicket(detail.id, assignTo ? Number(assignTo) : null),
                )
              }
            >
              Save assignment
            </Button>
            <Button
              type="button"
              size="sm"
              variant="secondary"
              disabled={busyKey !== null}
              data-testid="support-forward-save"
              onClick={() =>
                void run(`forward-${detail.id}`, () =>
                  forwardSupportTicket(detail.id, forwardTo ? Number(forwardTo) : null),
                )
              }
            >
              Save forward
            </Button>
            <Button
              type="button"
              size="sm"
              variant="secondary"
              disabled={busyKey !== null}
              data-testid="support-status-save"
              onClick={() =>
                void run(`status-${detail.id}`, () =>
                  updateSupportTicketStatus(portal, detail.id, statusValue),
                )
              }
            >
              Update status
            </Button>
          </div>

          <div className="space-y-2">
            <textarea
              className="w-full rounded-lg border border-jp-border p-2 text-sm"
              placeholder="Reply body"
              value={replyBody}
              onChange={(e) => setReplyBody(e.target.value)}
              data-testid="support-reply-input"
            />
            <div className="flex flex-wrap items-center gap-2">
              <select
                className="rounded-lg border border-jp-border px-3 py-2 text-sm"
                value={replyVisibility}
                onChange={(e) =>
                  setReplyVisibility(e.target.value as "internal" | "customer_visible")
                }
                data-testid="support-reply-visibility"
              >
                <option value="internal">Internal note</option>
                <option value="customer_visible">Customer-visible reply</option>
              </select>
              <Button
                type="button"
                size="sm"
                disabled={busyKey !== null}
                data-testid="support-reply-send"
                onClick={() => {
                  const body = replyBody.trim();
                  if (!body) {
                    setError("Reply body is required.");
                    return;
                  }
                  void run(`reply-${detail.id}`, async () => {
                    const result = await replySupportTicket(
                      portal,
                      detail.id,
                      body,
                      replyVisibility,
                    );
                    if (result.ok) setReplyBody("");
                    return result;
                  });
                }}
              >
                Send reply
              </Button>
            </div>
            <p className="text-xs text-jp-muted">
              Production QA: prefer internal notes. Customer-visible replies must not contact real customers.
            </p>
          </div>
        </section>
      ) : null}
    </div>
  );
}
