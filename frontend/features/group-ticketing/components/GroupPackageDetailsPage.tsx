"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { BookingProgress } from "@/features/booking-progress";
import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { fetchGroupPackage } from "../services/group-ticketing-api";
import type { GroupPackage } from "../types";
import { GroupAvailabilityBadge, GroupPackageHero, GroupPriceBlock } from "./GroupPackageBlocks";
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
  const [progress, setProgress] = useState<Array<{ key: string; label: string; state: "completed" | "current" | "upcoming"; href?: string | null }>>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    void fetchGroupPackage(packageId).then((response) => {
      setLoading(false);
      if (!response.ok) {
        setError(response.message);
        return;
      }
      setPkg(response.data.package);
      setAvailable(response.data.available);
      setLocked(response.data.lock_state.locked);
      setLockedMessage(response.data.lock_state.message ?? undefined);
      setProgress(response.data.progress as typeof progress);
    });
  }, [packageId]);

  if (loading) return <p className="p-8 text-jp-sm text-jp-muted">Loading package details…</p>;
  if (locked) return <div className="p-8"><GroupLockedState message={lockedMessage} /></div>;
  if (error || !pkg) return <div className="p-8"><GroupUnavailableState /></div>;
  if (!available || pkg.available_seats <= 0) return <div className="p-8"><GroupUnavailableState /></div>;

  return (
    <div className="mx-auto max-w-4xl px-4 py-8">
      <BookingProgress steps={progress} ariaLabel="Group booking progress" className="mb-6" />
      <GroupPackageHero package={pkg} />
      <div className="mt-6 grid gap-4 md:grid-cols-[2fr_1fr]">
        <section className="space-y-4 rounded-jp-lg border border-jp-border bg-jp-surface p-4">
          <GroupAvailabilityBadge availableSeats={pkg.available_seats} seatLabel={pkg.seat_label} variant={pkg.seats_badge_variant} />
          {pkg.baggage_line ? <p className="text-jp-sm text-jp-text">{pkg.baggage_line}</p> : null}
          {pkg.package_notes ? <p className="text-jp-sm text-jp-muted">{pkg.package_notes}</p> : null}
          <p className="text-jp-sm text-jp-muted">
            Payment is manual only. Your seats will be held for {pkg.booking_conditions?.hold_minutes ?? 25} minutes after you confirm on the review step.
          </p>
          {pkg.seat_selection?.message ? <p className="text-jp-sm text-jp-muted">{pkg.seat_selection.message}</p> : null}
        </section>
        <GroupPriceBlock currency={pkg.currency} priceFormatted={pkg.price_formatted} />
      </div>
      <div className="mt-6 flex flex-wrap gap-3">
        <PrimaryButton onClick={() => router.push(`/groups/${encodeURIComponent(packageId)}/passengers`)}>
          Continue to passengers
        </PrimaryButton>
        <Link href="/groups/search" className="inline-flex min-h-jp-button items-center rounded-jp-button border border-jp-border px-4 text-jp-sm font-semibold">
          Back to search
        </Link>
      </div>
    </div>
  );
}
