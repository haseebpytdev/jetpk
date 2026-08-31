"use client";

import { useEffect, useState, type FormEvent } from "react";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import { loadBookingCheckoutSettings, updateBookingCheckoutSettings } from "@/services/operational-api";

type CheckoutFormState = {
  guest_booking_enabled: boolean;
  card_payment_enabled: boolean;
  customer_group_booking_enabled: boolean;
};

const emptyForm: CheckoutFormState = {
  guest_booking_enabled: true,
  card_payment_enabled: true,
  customer_group_booking_enabled: true,
};

function settingsToForm(settings: Record<string, unknown> | undefined): CheckoutFormState {
  if (!settings) return emptyForm;
  return {
    guest_booking_enabled: settings.guest_booking_enabled !== false,
    card_payment_enabled: settings.card_payment_enabled !== false,
    customer_group_booking_enabled: settings.customer_group_booking_enabled !== false,
  };
}

export function BookingCheckoutWorkspace() {
  const isLive = useDashboardLiveMode();
  const [form, setForm] = useState<CheckoutFormState>(emptyForm);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);

  useEffect(() => {
    if (!isLive) return;
    void (async () => {
      const result = await loadBookingCheckoutSettings();
      if (!result.ok) {
        setError(result.message ?? "Unable to load booking & checkout settings.");
        return;
      }
      setForm(settingsToForm(result.settings as Record<string, unknown> | undefined));
    })();
  }, [isLive]);

  async function onSave(event: FormEvent) {
    event.preventDefault();
    if (busy) return;
    setBusy(true);
    setError(null);
    setMessage(null);

    const result = await updateBookingCheckoutSettings({
      guest_booking_enabled: form.guest_booking_enabled,
      card_payment_enabled: form.card_payment_enabled,
      customer_group_booking_enabled: form.customer_group_booking_enabled,
    });

    setBusy(false);
    if (!result.ok) {
      setError(result.message ?? "Unable to save booking & checkout settings.");
      return;
    }

    setForm(settingsToForm(result.settings as Record<string, unknown> | undefined));
    setMessage("Booking & checkout settings saved.");
  }

  return (
    <div className="space-y-4" data-testid="booking-checkout-workspace">
      {!isLive ? (
        <p className="text-xs text-jp-muted">Booking & checkout settings are available in live dashboard mode only.</p>
      ) : null}
      {error ? <p className="text-sm text-red-600">{error}</p> : null}
      {message ? <p className="text-sm text-emerald-700">{message}</p> : null}

      <form onSubmit={onSave} className="rounded-xl border border-jp-border bg-white p-4 space-y-4">
        <div>
          <h3 className="text-sm font-semibold text-gray-900">Guest Booking</h3>
          <p className="mt-1 text-xs text-jp-muted">
            Allow visitors without a JetPakistan account to complete normal public Flight checkout.
            When disabled, anonymous visitors may search and view fares but must sign in or register
            before entering Traveler Details.
          </p>
          <label className="mt-3 flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              checked={form.guest_booking_enabled}
              onChange={(event) => setForm((current) => ({ ...current, guest_booking_enabled: event.target.checked }))}
              data-testid="guest-booking-enabled-toggle"
            />
            Enabled
          </label>
        </div>

        <div>
          <h3 className="text-sm font-semibold text-gray-900">Customer Group Booking</h3>
          <p className="mt-1 text-xs text-jp-muted">
            When disabled, authenticated Customers cannot book Group offers. Approved Agents remain eligible.
            Group booking always requires authentication — this never enables anonymous Group checkout.
          </p>
          <label className="mt-3 flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              checked={form.customer_group_booking_enabled}
              onChange={(event) =>
                setForm((current) => ({ ...current, customer_group_booking_enabled: event.target.checked }))
              }
              data-testid="customer-group-booking-enabled-toggle"
            />
            Enabled
          </label>
        </div>

        <div>
          <h3 className="text-sm font-semibold text-gray-900">Card payment</h3>
          <p className="mt-1 text-xs text-jp-muted">
            When disabled, card payment is hidden on review and AbhiPay start is rejected even if the gateway is active.
          </p>
          <label className="mt-3 flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              checked={form.card_payment_enabled}
              onChange={(event) => setForm((current) => ({ ...current, card_payment_enabled: event.target.checked }))}
              data-testid="card-payment-enabled-toggle"
            />
            Allow card payment
          </label>
        </div>

        <button
          type="submit"
          disabled={busy || !isLive}
          className="rounded-lg bg-jp-brand px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
          data-testid="booking-checkout-save"
        >
          {busy ? "Saving…" : "Save settings"}
        </button>
      </form>
    </div>
  );
}
