"use client";

import { useEffect, useMemo, useState } from "react";
import { useRouter } from "next/navigation";
import { BookingProgress } from "@/features/booking-progress";
import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { mapFieldErrors } from "@/features/auth/utils/laravel-auth-api";
import { fetchGroupPassengersContext, submitGroupPassengers } from "../services/group-ticketing-api";
import type { GroupContactDetails, GroupPassenger, GroupPassengersContext } from "../types";
import { GroupLockedState, GroupUnavailableState } from "./GroupStateCards";
import { GroupPriceBlock } from "./GroupPackageBlocks";

const TITLES = ["Mr", "Mrs", "Ms", "Miss", "Dr", "Mstr"];

function emptyPassenger(): GroupPassenger {
  return {
    title: "Mr",
    first_name: "",
    last_name: "",
    gender: "male",
    date_of_birth: "",
    nationality: "Pakistani",
    document_type: "passport",
    passport_number: "",
    passport_issue_date: "",
    passport_expiry: "",
    passenger_type: "adult",
  };
}

type GroupPassengersPageProps = {
  packageId: string;
};

export function GroupPassengersPage({ packageId }: GroupPassengersPageProps) {
  const router = useRouter();
  const [context, setContext] = useState<GroupPassengersContext | null>(null);
  const [seatCount, setSeatCount] = useState(1);
  const [passengers, setPassengers] = useState<GroupPassenger[]>([emptyPassenger()]);
  const [contact, setContact] = useState<GroupContactDetails>({
    contact_name: "",
    contact_email: "",
    contact_phone: "",
  });
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [submitting, setSubmitting] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

    useEffect(() => {
    void fetchGroupPassengersContext(packageId).then((response) => {
      setLoading(false);
      if (!response.ok) {
        if (response.status === 401) {
          router.push(`/login?redirect=${encodeURIComponent(`/groups/${packageId}/passengers`)}`);
          return;
        }
        setError(response.message);
        return;
      }
      setContext(response.data);
      setSeatCount(response.data.seat_count);
      setPassengers(Array.from({ length: response.data.seat_count }, () => emptyPassenger()));
    });
  }, [packageId, router]);

  useEffect(() => {
    setPassengers((current) => {
      if (current.length === seatCount) return current;
      if (current.length < seatCount) {
        return [...current, ...Array.from({ length: seatCount - current.length }, () => emptyPassenger())];
      }
      return current.slice(0, seatCount);
    });
  }, [seatCount]);

  const totalFormatted = useMemo(() => {
    if (!context) return undefined;
    const perSeat = Number(String(context.inventory.price_formatted).replace(/,/g, ""));
    return Number.isFinite(perSeat) ? String(perSeat * seatCount) : undefined;
  }, [context, seatCount]);

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault();
    setSubmitting(true);
    setFieldErrors({});

    const formData = new FormData();
    formData.set("seat_count", String(seatCount));
    formData.set("contact_name", contact.contact_name);
    formData.set("contact_email", contact.contact_email);
    formData.set("contact_phone", contact.contact_phone);
    passengers.forEach((passenger, index) => {
      Object.entries(passenger).forEach(([key, value]) => {
        if (value) formData.set(`passengers[${index}][${key}]`, value);
      });
    });

    const response = await submitGroupPassengers(packageId, formData);
    setSubmitting(false);

    if (!response.ok) {
      setFieldErrors(mapFieldErrors(response.errors));
      setError(response.message);
      return;
    }

    router.push(response.data.redirect_path);
  };

  if (loading) return <p className="p-8 text-jp-sm text-jp-muted">Loading passenger form…</p>;
  if (context?.lock_state?.locked) return <div className="p-8"><GroupLockedState message={context.lock_state.message ?? undefined} /></div>;
  if (error && !context) return <div className="p-8"><GroupUnavailableState /></div>;
  if (!context) return null;

  return (
    <div className="mx-auto max-w-5xl px-4 py-8">
      <BookingProgress steps={context.progress} className="mb-6" />
      <h1 className="text-2xl font-semibold text-jp-text">Passenger details</h1>
      <form onSubmit={(event) => void handleSubmit(event)} className="mt-6 grid gap-6 lg:grid-cols-[2fr_1fr]" data-testid="group-passengers-form">
        <div className="space-y-4">
          <div className="rounded-jp-lg border border-jp-border bg-jp-surface p-4">
            <label htmlFor="seat_count" className="mb-1 block text-jp-sm font-semibold">Number of seats</label>
            <select
              id="seat_count"
              value={seatCount}
              onChange={(event) => setSeatCount(Number(event.target.value))}
              className="w-full rounded-jp-md border border-jp-border px-3 py-2"
            >
              {Array.from({ length: context.max_seats }, (_, index) => index + 1).map((value) => (
                <option key={value} value={value}>{value}</option>
              ))}
            </select>
            {fieldErrors.seat_count ? <p className="mt-1 text-jp-sm text-red-700">{fieldErrors.seat_count}</p> : null}
          </div>

          {passengers.map((passenger, index) => (
            <fieldset key={index} className="rounded-jp-lg border border-jp-border bg-jp-surface p-4">
              <legend className="px-1 text-jp-sm font-semibold">Passenger {index + 1}</legend>
              <div className="mt-3 grid gap-3 sm:grid-cols-2">
                <label className="text-jp-sm">Title
                  <select value={passenger.title} onChange={(e) => setPassengers((rows) => rows.map((row, i) => i === index ? { ...row, title: e.target.value } : row))} className="mt-1 w-full rounded-jp-md border border-jp-border px-3 py-2">
                    {TITLES.map((title) => <option key={title} value={title}>{title}</option>)}
                  </select>
                </label>
                <label className="text-jp-sm">Gender
                  <select value={passenger.gender} onChange={(e) => setPassengers((rows) => rows.map((row, i) => i === index ? { ...row, gender: e.target.value } : row))} className="mt-1 w-full rounded-jp-md border border-jp-border px-3 py-2">
                    <option value="male">Male</option>
                    <option value="female">Female</option>
                    <option value="other">Other</option>
                  </select>
                </label>
                <label className="text-jp-sm">First name
                  <input required value={passenger.first_name} onChange={(e) => setPassengers((rows) => rows.map((row, i) => i === index ? { ...row, first_name: e.target.value } : row))} className="mt-1 w-full rounded-jp-md border border-jp-border px-3 py-2" />
                </label>
                <label className="text-jp-sm">Last name
                  <input required value={passenger.last_name} onChange={(e) => setPassengers((rows) => rows.map((row, i) => i === index ? { ...row, last_name: e.target.value } : row))} className="mt-1 w-full rounded-jp-md border border-jp-border px-3 py-2" />
                </label>
                <label className="text-jp-sm">Date of birth
                  <input required type="date" value={passenger.date_of_birth} onChange={(e) => setPassengers((rows) => rows.map((row, i) => i === index ? { ...row, date_of_birth: e.target.value } : row))} className="mt-1 w-full rounded-jp-md border border-jp-border px-3 py-2" />
                </label>
                <label className="text-jp-sm">Nationality
                  <input required value={passenger.nationality} onChange={(e) => setPassengers((rows) => rows.map((row, i) => i === index ? { ...row, nationality: e.target.value } : row))} className="mt-1 w-full rounded-jp-md border border-jp-border px-3 py-2" />
                </label>
                <label className="text-jp-sm">Document type
                  <select value={passenger.document_type} onChange={(e) => setPassengers((rows) => rows.map((row, i) => i === index ? { ...row, document_type: e.target.value as GroupPassenger["document_type"] } : row))} className="mt-1 w-full rounded-jp-md border border-jp-border px-3 py-2">
                    <option value="passport">Passport</option>
                    <option value="national_id">National ID</option>
                  </select>
                </label>
                <label className="text-jp-sm">Document number
                  <input required value={passenger.passport_number} onChange={(e) => setPassengers((rows) => rows.map((row, i) => i === index ? { ...row, passport_number: e.target.value } : row))} className="mt-1 w-full rounded-jp-md border border-jp-border px-3 py-2" />
                </label>
                <label className="text-jp-sm">Document expiry
                  <input required type="date" value={passenger.passport_expiry} onChange={(e) => setPassengers((rows) => rows.map((row, i) => i === index ? { ...row, passport_expiry: e.target.value } : row))} className="mt-1 w-full rounded-jp-md border border-jp-border px-3 py-2" />
                </label>
              </div>
            </fieldset>
          ))}

          <fieldset className="rounded-jp-lg border border-jp-border bg-jp-surface p-4">
            <legend className="px-1 text-jp-sm font-semibold">Contact details</legend>
            <div className="mt-3 grid gap-3">
              <label className="text-jp-sm">Contact name
                <input required value={contact.contact_name} onChange={(e) => setContact((value) => ({ ...value, contact_name: e.target.value }))} className="mt-1 w-full rounded-jp-md border border-jp-border px-3 py-2" />
              </label>
              <label className="text-jp-sm">Email
                <input required type="email" value={contact.contact_email} onChange={(e) => setContact((value) => ({ ...value, contact_email: e.target.value }))} className="mt-1 w-full rounded-jp-md border border-jp-border px-3 py-2" />
              </label>
              <label className="text-jp-sm">Phone
                <input required value={contact.contact_phone} onChange={(e) => setContact((value) => ({ ...value, contact_phone: e.target.value }))} className="mt-1 w-full rounded-jp-md border border-jp-border px-3 py-2" />
              </label>
            </div>
          </fieldset>

          {error ? <p className="text-jp-sm text-red-700" role="alert">{error}</p> : null}
          <PrimaryButton type="submit" disabled={submitting}>{submitting ? "Saving…" : "Continue to review"}</PrimaryButton>
        </div>

        <aside>
          <GroupPriceBlock
            currency={context.inventory.currency}
            priceFormatted={context.inventory.price_formatted}
            seatCount={seatCount}
            totalFormatted={totalFormatted}
          />
        </aside>
      </form>
    </div>
  );
}
