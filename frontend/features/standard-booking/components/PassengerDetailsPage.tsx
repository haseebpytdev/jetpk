"use client";

import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { useRouter } from "next/navigation";
import { BookingProgress } from "@/features/booking-progress";
import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { mapFieldErrors } from "@/features/auth/utils/laravel-auth-api";
import { fetchStandardPassengersContext, submitStandardPassengers } from "../services/standard-booking-api";
import type { ContactFormValues, PassengerFormValues, StandardPassengersContext } from "../types";
import {
  buildContactFromContext,
  buildPassengerFormData,
  buildPassengersFromContext,
  passengerLabel,
} from "../utils/passenger-form";
import { isAllowedBookingNextUrl, resolveBookingNextUrl } from "../utils/allowlist";
import { BookingSessionCountdown } from "./BookingSessionCountdown";
import { SelectedFlightSummaryCard } from "./SelectedFlightSummaryCard";
import {
  BookingSessionExpiredState,
  MissingBookingSessionState,
  OfferExpiredState,
  SeatExtrasReadinessPanel,
  SupplierRequirementsUnavailableState,
} from "./BookingStateCards";
import { PassengerCard } from "./PassengerCard";
import { ContactDetailsSection } from "./ContactDetailsSection";

type PassengerDetailsPageProps = {
  searchParams: Record<string, string | undefined>;
};

export function PassengerDetailsPage({ searchParams }: PassengerDetailsPageProps) {
  const router = useRouter();
  const [context, setContext] = useState<StandardPassengersContext | null>(null);
  const [passengers, setPassengers] = useState<PassengerFormValues[]>([]);
  const [contact, setContact] = useState<ContactFormValues>({ contact_name: "", email: "", phone: "", phone_country_code: "+92", phone_number: "", country: "" });
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [expired, setExpired] = useState(false);
  const [errorStatus, setErrorStatus] = useState<string | null>(null);
  const [errorRedirect, setErrorRedirect] = useState<string | null>(null);
  const submitLock = useRef(false);
  const errorSummaryRef = useRef<HTMLDivElement>(null);

  const queryKey = useMemo(() => JSON.stringify(searchParams), [searchParams]);

  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    setFormError(null);
    setErrorStatus(null);

    void fetchStandardPassengersContext(searchParams).then((response) => {
      if (cancelled) return;
      setLoading(false);

      if (!response.ok) {
        const data = response.data as { status?: string; redirect_url?: string } | undefined;
        setErrorStatus(data?.status ?? (response.status === 404 ? "missing_session" : "error"));
        setErrorRedirect(data?.redirect_url ?? null);
        setFormError(response.message);
        return;
      }

      if (!response.data.ok) {
        setErrorStatus("error");
        setFormError("Unable to load passenger form.");
        return;
      }

      setContext(response.data);
      setPassengers(buildPassengersFromContext(response.data));
      setContact(buildContactFromContext(response.data));
    });

    return () => {
      cancelled = true;
    };
  }, [queryKey, searchParams]);

  const updatePassenger = useCallback((index: number, field: keyof PassengerFormValues, value: string) => {
    setPassengers((rows) => rows.map((row, i) => (i === index ? { ...row, [field]: value } : row)));
  }, []);

  const updateContact = useCallback((field: keyof ContactFormValues, value: string | boolean) => {
    setContact((current) => ({ ...current, [field]: value }));
  }, []);

  const focusFirstError = useCallback((errors: Record<string, string>) => {
    const firstKey = Object.keys(errors)[0];
    if (!firstKey) return;
    const el = document.querySelector<HTMLElement>(`[name="${firstKey}"], [aria-invalid="true"]`);
    el?.focus();
    errorSummaryRef.current?.focus();
  }, []);

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault();
    if (!context || submitLock.current || expired) return;

    submitLock.current = true;
    setSubmitting(true);
    setFieldErrors({});
    setFormError(null);

    const formData = buildPassengerFormData(context, passengers, contact);
    const response = await submitStandardPassengers(formData);

    setSubmitting(false);
    submitLock.current = false;

    if (!response.ok) {
      const data = response.data as { status?: string; redirect_url?: string } | undefined;
      if (response.status === 410 || data?.status === "offer_expired") {
        setErrorStatus("offer_expired");
        setErrorRedirect(data?.redirect_url ?? null);
      } else if (response.status === 404 || data?.status === "missing_session") {
        setErrorStatus("missing_session");
      } else {
        const mapped = mapFieldErrors(response.errors);
        setFieldErrors(mapped);
        setFormError(response.message);
        focusFirstError(mapped);
      }
      return;
    }

    const nextUrl = response.data.next_url;
    if (!nextUrl || !isAllowedBookingNextUrl(nextUrl)) {
      setFormError("Invalid next step from server. Please contact support.");
      return;
    }

    const resolved = resolveBookingNextUrl(nextUrl);
    if (!resolved) {
      setFormError("Invalid next step from server. Please contact support.");
      return;
    }

    router.push(resolved);
  };

  if (loading) {
    return <p className="p-8 text-jp-sm text-jp-muted" data-testid="passengers-loading">Loading passenger form…</p>;
  }

  if (errorStatus === "missing_session") {
    return <div className="mx-auto max-w-3xl p-8"><MissingBookingSessionState /></div>;
  }

  if (errorStatus === "offer_expired") {
    return <div className="mx-auto max-w-3xl p-8"><OfferExpiredState redirectUrl={errorRedirect} /></div>;
  }

  if (expired) {
    return <div className="mx-auto max-w-3xl p-8"><BookingSessionExpiredState /></div>;
  }

  if (!context) {
    return <div className="mx-auto max-w-3xl p-8"><SupplierRequirementsUnavailableState /></div>;
  }

  const typeOrdinals: Record<string, number> = { adult: 0, child: 0, infant: 0 };

  return (
    <div className="mx-auto max-w-6xl px-4 py-8">
      <BookingProgress steps={context.booking_session.progress} className="mb-6" />

      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold text-jp-text">Passenger details</h1>
          <p className="mt-1 text-jp-sm text-jp-muted">Enter details exactly as shown on travel documents.</p>
        </div>
        <BookingSessionCountdown
          expiresAt={context.booking_session.expires_at}
          serverTime={context.booking_session.server_time}
          onExpired={() => setExpired(true)}
        />
      </div>

      {context.validation_alert ? (
        <p className="mt-4 rounded-jp-md border border-amber-200 bg-amber-50 p-3 text-jp-sm text-amber-900" role="status">
          {context.validation_alert}
        </p>
      ) : null}

      <form
        onSubmit={(event) => void handleSubmit(event)}
        className="mt-6 grid gap-6 lg:grid-cols-[2fr_1fr]"
        data-testid="standard-passengers-form"
        noValidate
      >
        <div className="space-y-4">
          {formError || Object.keys(fieldErrors).length > 0 ? (
            <div
              ref={errorSummaryRef}
              tabIndex={-1}
              className="rounded-jp-md border border-red-200 bg-red-50 p-3 text-jp-sm text-red-800"
              role="alert"
              data-testid="passenger-validation-summary"
            >
              {formError ?? "Please correct the highlighted fields."}
            </div>
          ) : null}

          {passengers.map((passenger, index) => {
            const slot = context.travellers.expected[index];
            const ordinal = (typeOrdinals[slot.type] ?? 0) + 1;
            typeOrdinals[slot.type] = ordinal;
            return (
              <PassengerCard
                key={index}
                index={index}
                label={passengerLabel(slot, ordinal)}
                isLead={index === context.travellers.lead_passenger_index}
                passenger={passenger}
                documentRequirements={context.document_requirements}
                nationalIdAllowed={context.document_requirements.national_id_allowed}
                fieldErrors={fieldErrors}
                onChange={updatePassenger}
              />
            );
          })}

          <ContactDetailsSection
            contact={contact}
            locked={context.auth.agent_contact_locked}
            canCreateAccount={context.auth.can_create_account}
            fieldErrors={fieldErrors}
            onChange={updateContact}
          />

          <SeatExtrasReadinessPanel message={context.seat_extras_capability.message} />

          <PrimaryButton
            type="submit"
            className="w-full sm:w-auto"
            disabled={submitting || expired}
            aria-busy={submitting}
            data-testid="save-and-continue"
          >
            {submitting ? "Saving…" : "Save and continue"}
          </PrimaryButton>
        </div>

        <div className="lg:sticky lg:top-4 lg:self-start">
          <SelectedFlightSummaryCard
            itinerary={context.itinerary}
            travellerTotal={context.travellers.total}
          />
        </div>
      </form>
    </div>
  );
}
