"use client";

import { useState } from "react";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import { updateBookingLocalContact } from "@/services/operational-api";
import { useDashboardPortal } from "@/lib/portal-context";
import type { BookingManagementDetail } from "@/types/booking";

type Props = {
  bookingId: string;
  localContact?: BookingManagementDetail["localContact"];
  localAmendment?: BookingManagementDetail["localAmendment"];
};

export function BookingLocalContactEditor({ bookingId, localContact, localAmendment }: Props) {
  const portal = useDashboardPortal();
  const isLive = useDashboardLiveMode();
  const [email, setEmail] = useState(localContact?.email ?? "");
  const [phone, setPhone] = useState(localContact?.phone ?? "");
  const [country, setCountry] = useState(localContact?.country ?? "");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  if (!isLive) {
    return (
      <p className="text-xs text-jp-muted" data-testid="booking-local-contact-preview">
        Local contact amendment is available in live dashboard mode only.
      </p>
    );
  }

  const canEdit = Boolean(localAmendment?.canEditContact);

  async function handleSave() {
    if (!canEdit) {
      return;
    }
    setBusy(true);
    setError(null);
    setSuccess(null);
    const result = await updateBookingLocalContact(portal, bookingId, {
      email: email.trim(),
      phone: phone.trim(),
      country: country.trim() || undefined,
    });
    setBusy(false);
    if (!result.ok) {
      setError(result.message ?? "Contact update failed.");
      return;
    }
    setSuccess("Local contact updated (JetPakistan record only).");
  }

  return (
    <div className="space-y-3" data-testid="booking-local-contact-editor">
      <p className="text-xs text-jp-muted" data-testid="booking-local-contact-policy">
        {localAmendment?.contactPolicy ??
          "Local JetPakistan contact record only — not synced to airline/supplier PNR."}
      </p>
      {!localAmendment?.canEditPassengers ? (
        <p className="text-xs text-jp-muted" data-testid="booking-local-passenger-policy">
          {localAmendment?.passengerPolicy}
        </p>
      ) : null}
      <div className="grid gap-3 sm:grid-cols-2">
        <label className="block text-sm">
          <span className="text-jp-muted">Email</span>
          <input
            type="email"
            className="mt-1 w-full rounded-xl border border-jp-border px-3 py-2"
            value={email}
            disabled={!canEdit || busy}
            onChange={(e) => setEmail(e.target.value)}
            data-testid="booking-local-contact-email"
          />
        </label>
        <label className="block text-sm">
          <span className="text-jp-muted">Phone</span>
          <input
            type="text"
            className="mt-1 w-full rounded-xl border border-jp-border px-3 py-2"
            value={phone}
            disabled={!canEdit || busy}
            onChange={(e) => setPhone(e.target.value)}
            data-testid="booking-local-contact-phone"
          />
        </label>
        <label className="block text-sm sm:col-span-2">
          <span className="text-jp-muted">Country</span>
          <input
            type="text"
            className="mt-1 w-full rounded-xl border border-jp-border px-3 py-2"
            value={country}
            disabled={!canEdit || busy}
            onChange={(e) => setCountry(e.target.value)}
            data-testid="booking-local-contact-country"
          />
        </label>
      </div>
      {error ? (
        <p className="text-sm text-red-700" data-testid="booking-local-contact-error">
          {error}
        </p>
      ) : null}
      {success ? (
        <p className="text-sm text-emerald-700" data-testid="booking-local-contact-success">
          {success}
        </p>
      ) : null}
      <button
        type="button"
        className="inline-flex min-h-11 items-center rounded-xl bg-jp-navy px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
        disabled={!canEdit || busy}
        onClick={() => void handleSave()}
        data-testid="booking-local-contact-save"
      >
        {busy ? "Saving…" : "Save local contact"}
      </button>
    </div>
  );
}
