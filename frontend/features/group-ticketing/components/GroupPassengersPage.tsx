"use client";

import { useEffect, useMemo, useState } from "react";
import { useRouter } from "next/navigation";
import { BookingProgress } from "@/features/booking-progress";
import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { FieldLabel, Select, TextInput } from "@/components/ui/FormControls";
import { mapFieldErrors } from "@/features/auth/utils/laravel-auth-api";
import { DocumentReader } from "@/features/standard-booking/document-reader";
import type { PassengerFormValues } from "@/features/standard-booking/types";
import { fetchGroupPassengersContext, submitGroupPassengers } from "../services/group-ticketing-api";
import type { GroupContactDetails, GroupPassenger, GroupPassengersContext } from "../types";
import { GroupCheckoutAuthModal } from "./GroupCheckoutAuthModal";
import {
  GroupCheckoutDecisionDialog,
  type GroupCheckoutDecisionModal,
} from "./GroupCheckoutDecisionDialog";
import { GroupLockedState, GroupUnavailableState } from "./GroupStateCards";
import { GroupBookingSummaryCard } from "./GroupPackageBlocks";

const TITLES = ["Mr", "Mrs", "Ms", "Miss", "Dr", "Mstr"];

function emptyPassenger(defaultNationality = "PK"): GroupPassenger {
  return {
    title: "Mr",
    first_name: "",
    last_name: "",
    gender: "male",
    date_of_birth: "",
    nationality: defaultNationality,
    document_type: "passport",
    passport_number: "",
    passport_issue_date: "",
    passport_expiry: "",
    passenger_type: "adult",
  };
}

function toFlightPassenger(p: GroupPassenger): PassengerFormValues {
  return {
    passenger_type: p.passenger_type || "adult",
    title: p.title,
    first_name: p.first_name,
    last_name: p.last_name,
    gender: p.gender,
    date_of_birth: p.date_of_birth,
    nationality: p.nationality,
    document_type: p.document_type,
    passport_number: p.passport_number,
    passport_issuing_country: p.nationality,
    passport_expiry_date: p.passport_expiry,
    passport_issue_date: p.passport_issue_date || "",
    national_id_number: "",
  };
}

function fromFlightPassenger(next: PassengerFormValues, current: GroupPassenger): GroupPassenger {
  return {
    ...current,
    title: next.title || current.title,
    first_name: next.first_name,
    last_name: next.last_name,
    gender: next.gender || current.gender,
    date_of_birth: next.date_of_birth,
    nationality: next.nationality || current.nationality,
    document_type: (next.document_type as GroupPassenger["document_type"]) || current.document_type,
    passport_number: next.passport_number,
    passport_expiry: next.passport_expiry_date,
    passport_issue_date: next.passport_issue_date || current.passport_issue_date,
  };
}

type GroupPassengersPageProps = {
  packageId: string;
};

export function GroupPassengersPage({ packageId }: GroupPassengersPageProps) {
  const router = useRouter();
  const passengersPath = `/groups/${encodeURIComponent(packageId)}/passengers`;
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
  const [authOpen, setAuthOpen] = useState(false);
  const [priceChange, setPriceChange] = useState<{
    currency: string;
    old_unit_price: number;
    new_unit_price: number;
    available_seats: number;
  } | null>(null);
  const [acceptPriceChange, setAcceptPriceChange] = useState(false);
  const [collapsed, setCollapsed] = useState<Record<number, boolean>>({});
  const [pendingReduction, setPendingReduction] = useState<GroupCheckoutDecisionModal | null>(null);
  const [keepIndexes, setKeepIndexes] = useState<number[]>([]);
  const [availabilityDecision, setAvailabilityDecision] = useState<GroupCheckoutDecisionModal | null>(null);
  const [availabilityAvailableSeats, setAvailabilityAvailableSeats] = useState<number | null>(null);

  useEffect(() => {
    void fetchGroupPassengersContext(packageId).then((response) => {
      setLoading(false);
      if (!response.ok) {
        if (response.status === 401) {
          setAuthOpen(true);
          return;
        }
        setError(response.message);
        return;
      }
      setContext(response.data);
      setSeatCount(response.data.seat_count);
      const defaultNat =
        response.data.countries.find((c) => c.code === "PK")?.code ??
        response.data.countries[0]?.code ??
        "PK";
      setPassengers(Array.from({ length: response.data.seat_count }, () => emptyPassenger(defaultNat)));
    });
  }, [packageId]);

  useEffect(() => {
    setPassengers((current) => {
      if (current.length === seatCount) return current;
      if (current.length < seatCount) {
        const defaultNat = context?.countries.find((c) => c.code === "PK")?.code ?? "PK";
        return [...current, ...Array.from({ length: seatCount - current.length }, () => emptyPassenger(defaultNat))];
      }
      // Explicit reduction: keep rows until the customer confirms which travelers to keep.
      return current;
    });
  }, [seatCount, context?.countries]);

  useEffect(() => {
    if (passengers.length <= seatCount) {
      setPendingReduction(null);
      setKeepIndexes([]);
      return;
    }
    setPendingReduction({
      title: "Select travelers to keep",
      body: `You reduced seats to ${seatCount}. Choose exactly ${seatCount} traveler(s) to keep. We will not drop passengers automatically.`,
      primary_action: `Keep ${seatCount} selected`,
      secondary_action: "Restore previous seat count",
    });
    setKeepIndexes((current) => current.filter((index) => index < passengers.length).slice(0, seatCount));
  }, [passengers.length, seatCount]);

  const totalFormatted = useMemo(() => {
    if (!context) return undefined;
    const perSeat = Number(String(context.inventory.price_formatted).replace(/,/g, ""));
    return Number.isFinite(perSeat) ? String(perSeat * seatCount) : undefined;
  }, [context, seatCount]);

  const applyPassengerReduction = () => {
    if (keepIndexes.length !== seatCount) {
      setError(`Select exactly ${seatCount} traveler(s) to keep.`);
      return;
    }
    setPassengers((rows) => keepIndexes.map((index) => rows[index]).filter(Boolean));
    setPendingReduction(null);
    setError(null);
  };

  const updatePassenger = (index: number, patch: Partial<GroupPassenger>) => {
    setPassengers((rows) => rows.map((row, i) => (i === index ? { ...row, ...patch } : row)));
  };

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault();
    if (passengers.length !== seatCount) {
      setError(`Select exactly ${seatCount} traveler(s) before continuing.`);
      return;
    }
    setSubmitting(true);
    setFieldErrors({});
    setError(null);

    const quotedUnit = Number(String(context?.inventory.price_formatted ?? "").replace(/,/g, ""));
    const formData = new FormData();
    formData.set("seat_count", String(seatCount));
    formData.set("contact_name", contact.contact_name);
    formData.set("contact_email", contact.contact_email);
    formData.set("contact_phone", contact.contact_phone);
    if (Number.isFinite(quotedUnit)) {
      formData.set("quoted_unit_price", String(quotedUnit));
    }
    if (acceptPriceChange) {
      formData.set("accept_price_change", "1");
    }
    passengers.forEach((passenger, index) => {
      Object.entries(passenger).forEach(([key, value]) => {
        if (value) formData.set(`passengers[${index}][${key}]`, value);
      });
    });

    const response = await submitGroupPassengers(packageId, formData);
    setSubmitting(false);

    if (!response.ok) {
      if (response.status === 401) {
        setAuthOpen(true);
        return;
      }
      if (response.status === 409) {
        const change = (response.data as { price_change?: typeof priceChange } | undefined)?.price_change;
        if (change) {
          setPriceChange(change);
          setError(response.message);
          return;
        }
      }
      const decision = (response.data as { checkout_decision?: { modal?: GroupCheckoutDecisionModal; available_seats?: number } } | undefined)
        ?.checkout_decision;
      if (decision?.modal) {
        setAvailabilityDecision(decision.modal);
        setAvailabilityAvailableSeats(decision.available_seats ?? null);
        setError(response.message);
        return;
      }
      setFieldErrors(mapFieldErrors(response.errors));
      setError(response.message);
      return;
    }

    router.push(response.data.redirect_path);
  };

  if (authOpen && !context) {
    return (
      <div className="mx-auto max-w-jp-container px-jp-xl py-8">
        <p className="mb-4 text-jp-sm text-jp-muted">Sign in to continue group checkout.</p>
        <GroupCheckoutAuthModal
          open={authOpen}
          onClose={() => router.push(`/groups/${encodeURIComponent(packageId)}`)}
          returnPath={passengersPath}
          onAuthenticated={(path) => {
            setAuthOpen(false);
            window.location.assign(path);
          }}
        />
      </div>
    );
  }

  if (loading) return <p className="p-8 text-jp-sm text-jp-muted">Loading passenger form…</p>;
  if (context?.lock_state?.locked) {
    return (
      <div className="p-8">
        <GroupLockedState message={context.lock_state.message ?? undefined} />
      </div>
    );
  }
  if (error && !context) {
    return (
      <div className="p-8">
        <GroupUnavailableState />
      </div>
    );
  }
  if (!context) return null;

  const countries = context.countries;

  return (
    <div className="mx-auto max-w-jp-container px-jp-xl py-6 font-[Inter,system-ui,sans-serif]" data-testid="group-passengers-page">
      <BookingProgress steps={context.progress} className="mb-5" />
      <h1 className="text-2xl font-semibold tracking-[-0.02em] text-jp-text">Passenger details</h1>
      <p className="mt-1 text-jp-sm text-jp-muted">Enter traveler details for your group seats. Review OCR suggestions before continuing.</p>

      <form
        onSubmit={(event) => void handleSubmit(event)}
        className="mt-5 grid gap-5 lg:grid-cols-[minmax(0,1.55fr)_minmax(17rem,0.9fr)] lg:items-start"
        data-testid="group-passengers-form"
      >
        <div className="space-y-3">
          <div className="rounded-jp-lg border border-jp-border bg-jp-surface p-3 sm:p-4">
            <FieldLabel htmlFor="seat_count" required>
              Number of seats
            </FieldLabel>
            <div className="mt-1 max-w-[10rem]">
              <Select
                id="seat_count"
                value={seatCount}
                onChange={(event) => setSeatCount(Number(event.target.value))}
                data-testid="group-seat-count"
              >
                {Array.from({ length: context.max_seats }, (_, index) => index + 1).map((value) => (
                  <option key={value} value={value}>
                    {value}
                  </option>
                ))}
              </Select>
            </div>
            {fieldErrors.seat_count ? <p className="mt-1 text-jp-sm text-red-700">{fieldErrors.seat_count}</p> : null}
          </div>

          {passengers.map((passenger, index) => {
            const isCollapsed = seatCount > 1 && collapsed[index] === true;
            const heading = `Passenger ${index + 1}`;
            return (
              <fieldset
                key={index}
                className="rounded-jp-lg border border-jp-border bg-jp-surface p-3 sm:p-4"
                data-testid={`group-passenger-${index}`}
              >
                <legend className="flex w-full items-center justify-between gap-2 px-1">
                  <span className="text-jp-sm font-semibold text-jp-text">{heading}</span>
                  <div className="flex items-center gap-3">
                    {pendingReduction ? (
                      <label className="flex items-center gap-1 text-jp-xs text-jp-text">
                        <input
                          type="checkbox"
                          checked={keepIndexes.includes(index)}
                          onChange={(event) => {
                            setKeepIndexes((current) => {
                              if (event.target.checked) {
                                if (current.includes(index) || current.length >= seatCount) {
                                  return current;
                                }
                                return [...current, index];
                              }
                              return current.filter((value) => value !== index);
                            });
                          }}
                          data-testid={`group-keep-passenger-${index}`}
                        />
                        Keep
                      </label>
                    ) : null}
                    {seatCount > 1 ? (
                      <button
                        type="button"
                        className="text-jp-xs font-semibold text-jp-primary"
                        onClick={() => setCollapsed((prev) => ({ ...prev, [index]: !isCollapsed }))}
                        data-testid={`group-passenger-toggle-${index}`}
                      >
                        {isCollapsed ? "Expand" : "Collapse"}
                      </button>
                    ) : null}
                  </div>
                </legend>

                {!isCollapsed ? (
                  <div className="mt-3 space-y-3">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                      <p className="text-jp-xs font-medium text-jp-muted">Passport scan assist (optional)</p>
                      <div data-testid={`group-passport-upload-${index}`}>
                        <DocumentReader
                          passengerIndex={index}
                          passenger={toFlightPassenger(passenger)}
                          onApply={(next) => updatePassenger(index, fromFlightPassenger(next, passenger))}
                        />
                      </div>
                    </div>

                    <div className="grid gap-2 sm:grid-cols-3">
                      <div>
                        <FieldLabel htmlFor={`p${index}-title`} required>
                          Title
                        </FieldLabel>
                        <Select
                          id={`p${index}-title`}
                          value={passenger.title}
                          onChange={(e) => updatePassenger(index, { title: e.target.value })}
                          className="mt-1"
                        >
                          {TITLES.map((title) => (
                            <option key={title} value={title}>
                              {title}
                            </option>
                          ))}
                        </Select>
                      </div>
                      <div>
                        <FieldLabel htmlFor={`p${index}-gender`} required>
                          Gender
                        </FieldLabel>
                        <Select
                          id={`p${index}-gender`}
                          value={passenger.gender}
                          onChange={(e) => updatePassenger(index, { gender: e.target.value })}
                          className="mt-1"
                        >
                          <option value="male">Male</option>
                          <option value="female">Female</option>
                          <option value="other">Other</option>
                        </Select>
                      </div>
                      <div>
                        <FieldLabel htmlFor={`p${index}-nationality`} required>
                          Nationality
                        </FieldLabel>
                        <Select
                          id={`p${index}-nationality`}
                          value={passenger.nationality}
                          onChange={(e) => updatePassenger(index, { nationality: e.target.value })}
                          className="mt-1"
                          data-testid={`group-nationality-${index}`}
                        >
                          {countries.map((country) => (
                            <option key={country.code} value={country.code}>
                              {country.name}
                            </option>
                          ))}
                        </Select>
                      </div>
                    </div>

                    <div className="grid gap-2 sm:grid-cols-2">
                      <div>
                        <FieldLabel htmlFor={`p${index}-first`} required>
                          First name
                        </FieldLabel>
                        <TextInput
                          id={`p${index}-first`}
                          required
                          value={passenger.first_name}
                          onChange={(e) => updatePassenger(index, { first_name: e.target.value })}
                          className="mt-1"
                          autoComplete="given-name"
                        />
                      </div>
                      <div>
                        <FieldLabel htmlFor={`p${index}-last`} required>
                          Last name
                        </FieldLabel>
                        <TextInput
                          id={`p${index}-last`}
                          required
                          value={passenger.last_name}
                          onChange={(e) => updatePassenger(index, { last_name: e.target.value })}
                          className="mt-1"
                          autoComplete="family-name"
                        />
                      </div>
                    </div>

                    <div>
                      <FieldLabel htmlFor={`p${index}-dob`} required>
                        Date of birth
                      </FieldLabel>
                      <TextInput
                        id={`p${index}-dob`}
                        required
                        type="date"
                        value={passenger.date_of_birth}
                        onChange={(e) => updatePassenger(index, { date_of_birth: e.target.value })}
                        className="mt-1 max-w-xs"
                      />
                    </div>

                    <div className="rounded-jp-md border border-jp-border/80 bg-jp-surface-muted/40 p-3">
                      <p className="mb-2 text-jp-xs font-semibold uppercase tracking-[0.12em] text-jp-muted">Travel document</p>
                      <div className="grid gap-2 sm:grid-cols-3">
                        <div>
                          <FieldLabel htmlFor={`p${index}-doctype`} required>
                            Type
                          </FieldLabel>
                          <Select
                            id={`p${index}-doctype`}
                            value={passenger.document_type}
                            onChange={(e) =>
                              updatePassenger(index, {
                                document_type: e.target.value as GroupPassenger["document_type"],
                              })
                            }
                            className="mt-1"
                          >
                            <option value="passport">Passport</option>
                            <option value="national_id">National ID</option>
                          </Select>
                        </div>
                        <div>
                          <FieldLabel htmlFor={`p${index}-docnum`} required>
                            Number
                          </FieldLabel>
                          <TextInput
                            id={`p${index}-docnum`}
                            required
                            value={passenger.passport_number}
                            onChange={(e) => updatePassenger(index, { passport_number: e.target.value })}
                            className="mt-1"
                          />
                        </div>
                        <div>
                          <FieldLabel htmlFor={`p${index}-docexp`} required>
                            Expiry
                          </FieldLabel>
                          <TextInput
                            id={`p${index}-docexp`}
                            required
                            type="date"
                            value={passenger.passport_expiry}
                            onChange={(e) => updatePassenger(index, { passport_expiry: e.target.value })}
                            className="mt-1"
                          />
                        </div>
                      </div>
                    </div>
                  </div>
                ) : (
                  <p className="mt-2 text-jp-sm text-jp-muted">
                    {passenger.first_name || passenger.last_name
                      ? `${passenger.title} ${passenger.first_name} ${passenger.last_name}`.trim()
                      : "Details collapsed — expand to edit."}
                  </p>
                )}
              </fieldset>
            );
          })}

          <fieldset className="rounded-jp-lg border border-jp-border bg-jp-surface p-3 sm:p-4">
            <legend className="px-1 text-jp-sm font-semibold text-jp-text">Contact details</legend>
            <div className="mt-3 grid gap-2 sm:grid-cols-3">
              <div className="sm:col-span-1">
                <FieldLabel htmlFor="contact_name" required>
                  Name
                </FieldLabel>
                <TextInput
                  id="contact_name"
                  required
                  value={contact.contact_name}
                  onChange={(e) => setContact((value) => ({ ...value, contact_name: e.target.value }))}
                  className="mt-1"
                />
              </div>
              <div>
                <FieldLabel htmlFor="contact_email" required>
                  Email
                </FieldLabel>
                <TextInput
                  id="contact_email"
                  required
                  type="email"
                  value={contact.contact_email}
                  onChange={(e) => setContact((value) => ({ ...value, contact_email: e.target.value }))}
                  className="mt-1"
                />
              </div>
              <div>
                <FieldLabel htmlFor="contact_phone" required>
                  Phone
                </FieldLabel>
                <TextInput
                  id="contact_phone"
                  required
                  value={contact.contact_phone}
                  onChange={(e) => setContact((value) => ({ ...value, contact_phone: e.target.value }))}
                  className="mt-1"
                />
              </div>
            </div>
          </fieldset>

          {priceChange ? (
            <div
              role="alertdialog"
              aria-labelledby="group-price-change-title"
              className="rounded-jp-md border border-amber-300 bg-amber-50 px-3 py-3 text-jp-sm text-amber-950"
              data-testid="group-price-change"
            >
              <p id="group-price-change-title" className="font-semibold">
                Fare updated
              </p>
              <p className="mt-1">
                Old per-seat fare: {priceChange.currency} {priceChange.old_unit_price.toLocaleString()}
              </p>
              <p>
                Updated per-seat fare: {priceChange.currency} {priceChange.new_unit_price.toLocaleString()}
              </p>
              <p className="mt-1 text-jp-xs">Available seats now: {priceChange.available_seats}</p>
              <label className="mt-3 flex items-start gap-2">
                <input
                  type="checkbox"
                  checked={acceptPriceChange}
                  onChange={(event) => setAcceptPriceChange(event.target.checked)}
                  className="mt-1"
                  data-testid="group-accept-price-change"
                />
                <span>I accept the updated per-seat group fare and want to continue.</span>
              </label>
            </div>
          ) : null}

          {error ? (
            <p className="text-jp-sm text-red-700" role="alert">
              {error}
            </p>
          ) : null}
          <PrimaryButton
            type="submit"
            disabled={
              submitting ||
              (Boolean(priceChange) && !acceptPriceChange) ||
              passengers.length !== seatCount
            }
            className="w-full sm:w-auto"
          >
            {submitting ? "Saving…" : "Continue to review"}
          </PrimaryButton>
        </div>

        <div className="lg:sticky lg:top-24">
          <GroupBookingSummaryCard
            package={context.inventory}
            seatCount={seatCount}
            totalFormatted={totalFormatted}
          />
        </div>
      </form>

      <GroupCheckoutAuthModal
        open={authOpen}
        onClose={() => setAuthOpen(false)}
        returnPath={passengersPath}
        onAuthenticated={(path) => {
          setAuthOpen(false);
          window.location.assign(path);
        }}
      />

      <GroupCheckoutDecisionDialog
        open={Boolean(pendingReduction)}
        modal={pendingReduction}
        onPrimary={applyPassengerReduction}
        onSecondary={() => {
          setSeatCount(passengers.length);
          setPendingReduction(null);
          setKeepIndexes([]);
        }}
        primaryDisabled={keepIndexes.length !== seatCount}
      />

      <GroupCheckoutDecisionDialog
        open={Boolean(availabilityDecision)}
        modal={availabilityDecision}
        onPrimary={
          availabilityAvailableSeats && availabilityAvailableSeats > 0
            ? () => {
                setSeatCount(availabilityAvailableSeats);
                setAvailabilityDecision(null);
              }
            : undefined
        }
        onSecondary={() => {
          setAvailabilityDecision(null);
          router.push("/groups/search");
        }}
      />
    </div>
  );
}
