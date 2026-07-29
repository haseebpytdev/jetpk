"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { PrimaryButton } from "@/components/ui/PrimaryButton";
import {
  createSupportTicket,
  fetchSupportCases,
  fetchSupportCreateForm,
  fetchSupportCaseDetail,
  replySupportTicket,
} from "../services/customer-dashboard-api";
import {
  CustomerDashboardErrorState,
  CustomerDashboardShell,
  CustomerEmptyState,
  StatusBadge,
} from "../shell/CustomerDashboardShell";
import type { CustomerSupportCase, CustomerSupportReply } from "../types";
import type { PublicSession } from "@/types/session";

const fieldClass =
  "mt-1 w-full rounded-jp-md border border-jp-border px-3 py-2 focus-visible:outline-none focus-visible:shadow-jp-focus";

export function CustomerSupportPage({ session }: { session: PublicSession }) {
  const [tickets, setTickets] = useState<CustomerSupportCase[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);

  const load = async () => {
    setLoading(true);
    const result = await fetchSupportCases();
    if (!result.ok) setError(result.message);
    else setTickets(result.data.tickets);
    setLoading(false);
  };

  useEffect(() => {
    void load();
  }, []);

  return (
    <CustomerDashboardShell session={session} title="Support">
      <div className="mb-4">
        <PrimaryButton type="button" onClick={() => setShowForm((value) => !value)}>
          {showForm ? "Hide form" : "New support request"}
        </PrimaryButton>
      </div>
      {showForm ? <NewSupportRequestForm onCreated={() => { setShowForm(false); void load(); }} /> : null}
      {loading ? <p className="text-jp-sm text-jp-muted">Loading support cases…</p> : null}
      {error ? <CustomerDashboardErrorState message={error} onRetry={load} /> : null}
      {!loading && !error && tickets.length === 0 ? (
        <CustomerEmptyState title="No support cases" description="Create a support request if you need help." />
      ) : null}
      <div className="space-y-3" data-testid="customer-support-list">
        {tickets.map((ticket) => (
          <article key={ticket.reference} className="rounded-jp-lg border border-jp-border bg-jp-surface p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
              <div>
                <Link href={`/customer/support/${ticket.reference}`} className="font-semibold text-jp-primary">
                  {ticket.reference}
                </Link>
                <p className="text-jp-sm text-jp-text">{ticket.subject}</p>
              </div>
              <StatusBadge status={ticket.status} />
            </div>
          </article>
        ))}
      </div>
    </CustomerDashboardShell>
  );
}

function NewSupportRequestForm({ onCreated }: { onCreated: () => void }) {
  const [categories, setCategories] = useState<Array<{ value: string; label: string }>>([]);
  const [bookings, setBookings] = useState<Array<{ id: number; booking_reference: string }>>([]);
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    void (async () => {
      const result = await fetchSupportCreateForm();
      if (result.ok) {
        setCategories(result.data.categories);
        setBookings(result.data.bookings);
      }
    })();
  }, []);

  const handleSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (submitting) return;
    setSubmitting(true);
    setError(null);
    const form = new FormData(event.currentTarget);
    const bookingId = form.get("booking_id");
    const result = await createSupportTicket({
      subject: String(form.get("subject") ?? ""),
      category: String(form.get("category") ?? ""),
      body: String(form.get("body") ?? ""),
      booking_id: bookingId ? Number(bookingId) : null,
    });
    if (!result.ok) setError(result.message);
    else onCreated();
    setSubmitting(false);
  };

  return (
    <form onSubmit={handleSubmit} className="mb-6 space-y-4 rounded-jp-lg border border-jp-border bg-jp-surface p-4" data-testid="new-support-request-form">
      {error ? <p className="text-jp-sm text-red-700" role="alert">{error}</p> : null}
      <label className="block text-jp-sm">
        Subject
        <input name="subject" className={fieldClass} required />
      </label>
      <label className="block text-jp-sm">
        Category
        <select name="category" className={fieldClass} required>
          {categories.map((category) => (
            <option key={category.value} value={category.value}>
              {category.label}
            </option>
          ))}
        </select>
      </label>
      <label className="block text-jp-sm">
        Related booking (optional)
        <select name="booking_id" className={fieldClass}>
          <option value="">None</option>
          {bookings.map((booking) => (
            <option key={booking.id} value={booking.id}>
              {booking.booking_reference}
            </option>
          ))}
        </select>
      </label>
      <label className="block text-jp-sm">
        Message
        <textarea name="body" rows={5} className={fieldClass} required />
      </label>
      <PrimaryButton type="submit" disabled={submitting}>
        {submitting ? "Submitting…" : "Submit request"}
      </PrimaryButton>
    </form>
  );
}

export function SupportCaseDetailPage({ session, reference }: { session: PublicSession; reference: string }) {
  const [ticket, setTicket] = useState<CustomerSupportCase | null>(null);
  const [conversation, setConversation] = useState<CustomerSupportReply[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [reply, setReply] = useState("");
  const [submitting, setSubmitting] = useState(false);

  const load = async () => {
    const result = await fetchSupportCaseDetail(reference);
    if (!result.ok) setError(result.message);
    else {
      setTicket(result.data.ticket);
      setConversation(result.data.conversation);
    }
  };

  useEffect(() => {
    void load();
  }, [reference]);

  const handleReply = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!reply.trim() || submitting) return;
    setSubmitting(true);
    const result = await replySupportTicket(reference, reply.trim());
    if (!result.ok) setError(result.message);
    else {
      setReply("");
      await load();
    }
    setSubmitting(false);
  };

  return (
    <CustomerDashboardShell session={session} title={`Support ${reference}`}>
      {error ? <CustomerDashboardErrorState message={error} onRetry={load} /> : null}
      {ticket ? (
        <div data-testid="support-case-detail">
          <h2 className="text-jp-base font-semibold">{ticket.subject}</h2>
          <StatusBadge status={ticket.status} />
          <div className="mt-6 space-y-3">
            {conversation.map((message) => (
              <article key={message.id} className="rounded-jp-md border border-jp-border bg-jp-surface-muted p-3">
                <p className="text-jp-xs font-semibold text-jp-muted">{message.author_name}</p>
                <p className="mt-1 whitespace-pre-wrap text-jp-sm text-jp-text">{message.body}</p>
              </article>
            ))}
          </div>
          {ticket.can_reply ? (
            <form onSubmit={handleReply} className="mt-6 space-y-3">
              <label className="block text-jp-sm">
                Reply
                <textarea value={reply} onChange={(event) => setReply(event.target.value)} rows={4} className={fieldClass} required />
              </label>
              <PrimaryButton type="submit" disabled={submitting}>
                {submitting ? "Sending…" : "Send reply"}
              </PrimaryButton>
            </form>
          ) : null}
        </div>
      ) : null}
    </CustomerDashboardShell>
  );
}
