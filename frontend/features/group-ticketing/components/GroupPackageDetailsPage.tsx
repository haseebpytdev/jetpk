"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { BookingProgress } from "@/features/booking-progress";
import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { fetchSessionBootstrap } from "@/features/auth/services/session-service";
import { fetchGroupPackage } from "../services/group-ticketing-api";
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
};

export function GroupPackageDetailsPage({ packageId }: GroupPackageDetailsPageProps) {
  const router = useRouter();
  const [pkg, setPkg] = useState<GroupPackage | null>(null);
  const [available, setAvailable] = useState(true);
  const [locked, setLocked] = useState(false);
  const [lockedMessage, setLockedMessage] = useState<string | undefined>();
  const [progress, setProgress] = useState<
    Array<{ key: string; label: string; state: "completed" | "current" | "upcoming"; href?: string | null }>
  >([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [authOpen, setAuthOpen] = useState(false);
  const [bookingBusy, setBookingBusy] = useState(false);
  const [refreshedAt, setRefreshedAt] = useState<string | null>(null);

  const passengersPath = `/groups/${encodeURIComponent(packageId)}/passengers`;

  const loadPackage = async () => {
    const response = await fetchGroupPackage(packageId);
    if (!response.ok) {
      setError(response.message);
      setPkg(null);
      return;
    }
    setError(null);
    setPkg(response.data.package);
    setAvailable(response.data.available);
    setLocked(response.data.lock_state.locked);
    setLockedMessage(response.data.lock_state.message ?? undefined);
    setProgress(response.data.progress as typeof progress);
    setRefreshedAt(new Date().toISOString());
  };

  useEffect(() => {
    setLoading(true);
    void loadPackage().finally(() => setLoading(false));
    // eslint-disable-next-line react-hooks/exhaustive-deps -- load once per packageId
  }, [packageId]);

  const handleBookNow = async () => {
    if (bookingBusy) return;
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
            disabled={bookingBusy}
            className="w-full"
            data-testid="group-book-now"
          >
            {bookingBusy ? "Checking…" : "Book Now"}
          </PrimaryButton>
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
