"use client";

import { useState } from "react";
import Link from "next/link";
import { ensureLaravelCsrfToken } from "@/features/auth/utils/laravel-auth-api";
import { laravelApiPath } from "@/services/flight-search";
import { PrimaryButton } from "@/components/ui/PrimaryButton";

export function BookingLookupPage() {
  const [bookingReference, setBookingReference] = useState("");
  const [email, setEmail] = useState("");
  const [phone, setPhone] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  const handleSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (submitting) return;

    setSubmitting(true);
    setError(null);

    try {
      const csrf = await ensureLaravelCsrfToken();
      const formData = new FormData();
      formData.set("booking_reference", bookingReference.trim());
      formData.set("email", email.trim());
      if (phone.trim()) formData.set("phone", phone.trim());

      const headers: Record<string, string> = {
        "X-Requested-With": "XMLHttpRequest",
      };
      if (csrf) headers["X-XSRF-TOKEN"] = csrf;

      const response = await fetch(laravelApiPath("/lookup-booking"), {
        method: "POST",
        body: formData,
        credentials: "include",
        redirect: "manual",
        headers,
      });

      if (response.type === "opaqueredirect" || (response.status >= 300 && response.status < 400)) {
        const location = response.headers.get("Location");
        if (location) {
          window.location.assign(location);
          return;
        }
      }

      if (response.ok) {
        const text = await response.text();
        if (text.includes("Booking not found")) {
          setError("Booking not found for the provided reference and email.");
        } else {
          window.location.reload();
        }
        return;
      }

      setError("Booking not found for the provided reference and email.");
    } catch {
      setError("We could not complete the lookup. Please try again.");
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="mx-auto max-w-4xl px-4 py-8" data-testid="booking-lookup-page">
      <header className="max-w-2xl">
        <p className="text-jp-xs font-semibold uppercase tracking-wide text-jp-primary">Manage booking</p>
        <h1 className="mt-1 text-2xl font-semibold text-jp-text">Lookup your booking</h1>
        <p className="mt-2 text-jp-sm text-jp-muted">
          Enter your booking reference and the email used when you booked. We verify your details before showing sensitive information.
        </p>
      </header>

      <div className="mt-8 grid gap-6 lg:grid-cols-[1fr_1.2fr]">
        <section className="rounded-jp-lg border border-jp-border bg-jp-surface p-4 text-jp-sm text-jp-muted">
          <h2 className="text-jp-base font-semibold text-jp-text">How lookup works</h2>
          <p className="mt-2">When your details match our records, you receive secure access to your booking documents and status.</p>
          <p className="mt-3">For privacy, we use a generic message when details do not match.</p>
        </section>

        <form className="rounded-jp-lg border border-jp-border bg-jp-surface p-4" onSubmit={(event) => void handleSubmit(event)} noValidate>
          <h2 className="text-jp-base font-semibold text-jp-text">Enter your details</h2>
          {error ? <p className="mt-3 text-jp-sm text-red-700" role="alert" data-testid="lookup-error">{error}</p> : null}
          <div className="mt-4 space-y-4">
            <label className="block text-jp-sm">
              <span className="font-medium text-jp-text">Booking reference</span>
              <input
                className="mt-1 w-full rounded-jp-md border border-jp-border px-3 py-2"
                name="booking_reference"
                value={bookingReference}
                onChange={(event) => setBookingReference(event.target.value)}
                autoComplete="off"
                required
              />
            </label>
            <label className="block text-jp-sm">
              <span className="font-medium text-jp-text">Email address</span>
              <input
                className="mt-1 w-full rounded-jp-md border border-jp-border px-3 py-2"
                name="email"
                type="email"
                value={email}
                onChange={(event) => setEmail(event.target.value)}
                autoComplete="email"
                required
              />
            </label>
            <label className="block text-jp-sm">
              <span className="font-medium text-jp-text">Phone (optional)</span>
              <input
                className="mt-1 w-full rounded-jp-md border border-jp-border px-3 py-2"
                name="phone"
                value={phone}
                onChange={(event) => setPhone(event.target.value)}
                autoComplete="tel"
              />
            </label>
          </div>
          <p className="mt-4 text-jp-xs text-jp-muted">For privacy, access is only granted when your details match the booking.</p>
          <PrimaryButton type="submit" className="mt-4" disabled={submitting} data-testid="lookup-submit">
            {submitting ? "Looking up…" : "Lookup booking"}
          </PrimaryButton>
        </form>
      </div>

      <nav className="mt-6 text-jp-sm print:hidden">
        <Link href="/support" className="text-jp-primary">Need help? Contact support</Link>
      </nav>
    </div>
  );
}
