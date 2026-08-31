"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { BookingProgress } from "@/features/booking-progress";
import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { fetchSessionBootstrap } from "@/features/auth/services/session-service";
import { fetchGroupPackage, type GroupPackagePayload } from "../services/group-ticketing-api";
import type { GroupPackage } from "../types";
import {
  GroupAvailabilityBadge,
  GroupBookingSummaryCard,
  GroupPackageHero,
} from "./GroupPackageBlocks";
import { GroupCheckoutAuthModal } from "./GroupCheckoutAuthModal";
import { GroupLockedState, GroupUnavailableState } from "./GroupStateCards";

type GroupPackageDetailsPageProps = {
  packageId: string;
  /** SSR payload — when present, skip blocking client waterfall on first paint. */
  initialPayload?: GroupPackagePayload | null;
};

function applyPayload(
  payload: GroupPackagePayload,
  setters: {
    setPkg: (pkg: GroupPackage) => void;
    setAvailable: (v: boolean) => void;
    setLocked: (v: boolean) => void;
    setLockedMessage: (v: string | undefined) => void;
    setProgress: (
      v: Array<{ key: string; label: string; state: "completed" | "current" | "upcoming"; href?: string | null }>,
    ) => void;
    setRefreshedAt: (v: string | null) => void;
    setError: (v: string | null) => void;
  },
) {
  setters.setError(null);
  setters.setPkg(payload.package);
  setters.setAvailable(payload.available);
  setters.setLocked(payload.lock_state.locked);
  setters.setLockedMessage(payload.lock_state.message ?? undefined);
  setters.setProgress(
    payload.progress as Array<{
      key: string;
      label: string;
      state: "completed" | "current" | "upcoming";
      href?: string | null;
    }>,
  );
  setters.setRefreshedAt(new Date().toISOString());
}

export function GroupPackageDetailsPage({ packageId, initialPayload = null }: GroupPackageDetailsPageProps) {
  const router = useRouter();
  const [pkg, setPkg] = useState<GroupPackage | null>(initialPayload?.package ?? null);
  const [available, setAvailable] = useState(initialPayload?.available ?? true);
  const [locked, setLocked] = useState(initialPayload?.lock_state.locked ?? false);
  const [lockedMessage, setLockedMessage] = useState<string | undefined>(
    initialPayload?.lock_state.message ?? undefined,
  );
  const [progress, setProgress] = useState<
    Array<{ key: string; label: string; state: "completed" | "current" | "upcoming"; href?: string | null }>
  >(
    (initialPayload?.progress as Array<{
      key: string;
      label: string;
      state: "completed" | "current" | "upcoming";
      href?: string | null;
    }>) ?? [],
  );
  const [loading, setLoading] = useState(!initialPayload?.package);
  const [error, setError] = useState<string | null>(null);
  const [authOpen, setAuthOpen] = useState(false);
  const [bookingBusy, setBookingBusy] = useState(false);
  const [refreshedAt, setRefreshedAt] = useState<string | null>(
    initialPayload?.package ? new Date().toISOString() : null,
  );

  const [shareBusy, setShareBusy] = useState(false);
  const [shareFeedback, setShareFeedback] = useState<string | null>(null);
  const [eligibility, setEligibility] = useState(initialPayload?.eligibility ?? null);
  const [sessionRole, setSessionRole] = useState<"anonymous" | "customer" | "agent" | "other">("anonymous");

  const passengersPath = `/groups/${encodeURIComponent(packageId)}/passengers`;

  const handleCopyShare = async () => {
    if (!pkg || shareBusy) return;
    setShareBusy(true);
    setShareFeedback(null);
    try {
      const response = await fetch("/api/public/share/group", {
        method: "POST",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
          "X-Requested-With": "XMLHttpRequest",
        },
        credentials: "same-origin",
        body: JSON.stringify({
          package_id: packageId,
          depart_date: pkg.departure_date ?? undefined,
          display_currency: pkg.currency ?? "PKR",
          title: pkg.title || pkg.route_line || "Group offer",
          route_label: pkg.route_line,
          seats_available: pkg.available_seats,
        }),
      });
      const data = (await response.json().catch(() => null)) as { ok?: boolean; url?: string; message?: string } | null;
      if (!response.ok || !data?.ok || !data.url) {
        setShareFeedback(data?.message ?? "Unable to create share link.");
        return;
      }
      await navigator.clipboard.writeText(data.url);
      setShareFeedback("Link copied");
    } catch {
      setShareFeedback("Unable to copy link.");
    } finally {
      setShareBusy(false);
    }
  };

  const loadPackage = async () => {
    const response = await fetchGroupPackage(packageId);
    if (!response.ok) {
      setError(response.message);
      setPkg(null);
      return;
    }
    applyPayload(response.data, {
      setPkg,
      setAvailable,
      setLocked,
      setLockedMessage,
      setProgress,
      setRefreshedAt,
      setError,
    });
    setEligibility(response.data.eligibility ?? null);
  };

  useEffect(() => {
    // SSR already hydrated package — refresh in background without blanking the shell.
    if (initialPayload?.package) {
      void loadPackage();
      return;
    }
    setLoading(true);
    void loadPackage().finally(() => setLoading(false));
    // eslint-disable-next-line react-hooks/exhaustive-deps -- load once per packageId
  }, [packageId]);

  useEffect(() => {
    void (async () => {
      const bootstrap = await fetchSessionBootstrap();
      if (!bootstrap.authenticated) {
        setSessionRole("anonymous");
        return;
      }
      const role = String(bootstrap.user?.account_type ?? bootstrap.role ?? "").toLowerCase();
      if (role.includes("agent")) {
        setSessionRole("agent");
      } else if (role.includes("customer")) {
        setSessionRole("customer");
      } else {
        setSessionRole("other");
      }
    })();
  }, []);

  const customerGroupBlocked =
    sessionRole === "customer" && eligibility?.customer_group_booking_enabled === false;

  const handleBookNow = async () => {
    if (bookingBusy || customerGroupBlocked) return;
    setBookingBusy(true);
    try {
      await loadPackage();
      const bootstrap = await fetchSessionBootstrap();
      if (bootstrap.authenticated) {
        router.push(passengersPath);
        return;
      }
      setAuthOpen(true);
    } finally {
      setBookingBusy(false);
    }
  };

  if (loading) return <p className="p-8 text-jp-sm text-jp-muted">Loading package details…</p>;
  if (locked)
    return (
      <div className="p-8">
        <GroupLockedState message={lockedMessage} />
      </div>
    );
  if (error || !pkg)
    return (
      <div className="p-8">
        <GroupUnavailableState />
      </div>
    );
  if (!available || pkg.available_seats <= 0)
    return (
      <div className="p-8">
        <GroupUnavailableState />
      </div>
    );

  return (
    <div
      className="mx-auto w-full max-w-jp-container px-jp-xl py-8 font-[Inter,system-ui,sans-serif]"
      data-testid="group-package-details"
    >
      <BookingProgress steps={progress} ariaLabel="Group booking progress" className="mb-6" />
      <div className="grid gap-5 lg:grid-cols-[minmax(0,1.55fr)_minmax(17rem,0.9fr)] lg:items-start">
        <div className="space-y-4">
          <GroupPackageHero package={pkg} />
          <section
            className="space-y-4 rounded-jp-lg border border-jp-border bg-jp-surface p-4 shadow-jp-sm"
            data-testid="group-detail-selected-preview"
          >
            <div className="flex flex-wrap items-center gap-2">
              <GroupAvailabilityBadge
                availableSeats={pkg.available_seats}
                seatLabel={pkg.seat_label}
                variant={pkg.seats_badge_variant}
              />
              {refreshedAt ? (
                <p className="text-jp-xs text-jp-muted" data-testid="group-seat-refreshed">
                  Availability refreshed (read-only).
                </p>
              ) : null}
            </div>
            <dl className="grid gap-3 text-jp-sm text-jp-text sm:grid-cols-2">
              <div>
                <dt className="text-jp-xs font-semibold uppercase tracking-[0.12em] text-jp-muted">From</dt>
                <dd className="font-medium">{pkg.origin_label ?? pkg.route_line}</dd>
              </div>
              <div>
                <dt className="text-jp-xs font-semibold uppercase tracking-[0.12em] text-jp-muted">To</dt>
                <dd className="font-medium">{pkg.dest_label ?? "—"}</dd>
              </div>
              <div>
                <dt className="text-jp-xs font-semibold uppercase tracking-[0.12em] text-jp-muted">Departure</dt>
                <dd className="font-medium">{pkg.departure_datetime_display ?? pkg.departure_date_short ?? "—"}</dd>
              </div>
              <div>
                <dt className="text-jp-xs font-semibold uppercase tracking-[0.12em] text-jp-muted">Arrival</dt>
                <dd className="font-medium">{pkg.arrival_time_display ?? "TBA"}</dd>
              </div>
              <div>
                <dt className="text-jp-xs font-semibold uppercase tracking-[0.12em] text-jp-muted">Sector</dt>
                <dd className="font-medium">{pkg.sector_code || "—"}</dd>
              </div>
              <div>
                <dt className="text-jp-xs font-semibold uppercase tracking-[0.12em] text-jp-muted">Baggage</dt>
                <dd className="font-medium">{pkg.baggage?.display ?? pkg.baggage_line ?? "—"}</dd>
              </div>
            </dl>
            {pkg.package_notes ? <p className="text-jp-sm text-jp-muted">{pkg.package_notes}</p> : null}
            <p className="text-jp-sm text-jp-muted">
              Payment is manual only. Seats are held for {pkg.booking_conditions?.hold_minutes ?? 25} minutes after review
              confirm. Supplier booking remains gated until authorized.
            </p>
          </section>
        </div>

        <div className="space-y-3 lg:sticky lg:top-24">
          <GroupBookingSummaryCard package={pkg} />
          <PrimaryButton
            onClick={() => void handleBookNow()}
            disabled={bookingBusy || customerGroupBlocked}
            className="w-full"
            data-testid="group-book-now"
          >
            {bookingBusy ? "Checking…" : customerGroupBlocked ? "Agents only" : "Book Now"}
          </PrimaryButton>
          {customerGroupBlocked ? (
            <div
              className="rounded-jp-md border border-jp-border bg-jp-page px-3 py-3 text-center"
              data-testid="group-customer-booking-disabled"
            >
              <p className="text-sm font-medium text-jp-text">
                This Group offer is currently available for registered JetPakistan Agents.
              </p>
              <div className="mt-3 flex flex-col gap-2">
                <Link
                  href="/login?redirect=/agent/dashboard"
                  className="inline-flex min-h-jp-button w-full items-center justify-center rounded-jp-button border border-jp-border px-4 text-jp-sm font-semibold"
                  data-testid="group-agent-login"
                >
                  Agent login
                </Link>
                <Link
                  href="/agent/register"
                  className="inline-flex min-h-jp-button w-full items-center justify-center rounded-jp-button border border-jp-border px-4 text-jp-sm font-semibold"
                  data-testid="group-agent-register"
                >
                  Agent registration
                </Link>
              </div>
            </div>
          ) : null}
          <button
            type="button"
            onClick={() => void handleCopyShare()}
            disabled={shareBusy}
            className="inline-flex min-h-jp-button w-full items-center justify-center rounded-jp-button border border-jp-border px-4 text-jp-sm font-semibold text-jp-text disabled:opacity-60"
            data-testid="group-copy-share"
          >
            {shareBusy ? "Creating link…" : "Copy share link"}
          </button>
          {shareFeedback ? (
            <p className="text-center text-jp-xs text-jp-muted" data-testid="group-share-feedback">
              {shareFeedback}
            </p>
          ) : null}
          <Link
            href="/groups/search"
            className="inline-flex min-h-jp-button w-full items-center justify-center rounded-jp-button border border-jp-border px-4 text-jp-sm font-semibold"
          >
            Back to search
          </Link>
        </div>
      </div>

      <GroupCheckoutAuthModal
        open={authOpen}
        onClose={() => setAuthOpen(false)}
        returnPath={passengersPath}
        onAuthenticated={(path) => {
          setAuthOpen(false);
          window.location.assign(path);
        }}
      />
    </div>
  );
}
