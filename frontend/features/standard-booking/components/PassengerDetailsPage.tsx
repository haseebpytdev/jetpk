"use client";

import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { useRouter } from "next/navigation";
import { BookingProgress } from "@/features/booking-progress";
import {
  BookingLayout,
  BookingMainColumn,
  BookingPageHeader,
  BookingPageShell,
  BookingSidebar,
  MobileOrderSummary,
  OrderSummary,
} from "@/features/booking-layout";
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
import {
  BookingSessionExpiredState,
  MissingBookingSessionState,
  OfferExpiredState,
  SeatExtrasReadinessPanel,
  SupplierRequirementsUnavailableState,
  NetworkErrorState,
  ServerErrorState,
  InvalidHandoffState,
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

  const loadContext = useCallback(() => {
    setLoading(true);
    setFormError(null);
    setErrorStatus(null);
    return fetchStandardPassengersContext(searchParams);
    // queryKey is the stable identity for searchParams contents
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [queryKey]);

  useEffect(() => {
    let cancelled = false;

    void loadContext().then((response) => {
      if (cancelled) return;
      setLoading(false);

      if (!response.ok) {
        const data = response.data as { status?: string; redirect_url?: string } | undefined;
        const apiStatus = (data?.status ?? "").toLowerCase();
        if (response.status === 0) {
          setErrorStatus("network_error");
        } else if (response.status === 404 || apiStatus === "missing_session") {
          setErrorStatus("missing_session");
        } else if (response.status === 410 || apiStatus === "offer_expired" || apiStatus === "checkout_expired") {
          setErrorStatus(apiStatus === "checkout_expired" ? "checkout_expired" : "offer_expired");
        } else if (apiStatus === "supplier_requirements_unavailable") {
          setErrorStatus("supplier_requirements_unavailable");
        } else if (apiStatus === "invalid_fare" || apiStatus === "invalid_handoff") {
          setErrorStatus("invalid_handoff");
        } else if (response.status >= 500) {
          setErrorStatus("server_error");
        } else {
          setErrorStatus("server_error");
        }
        setErrorRedirect(data?.redirect_url ?? null);
        setFormError(response.message);
        return;
      }

      if (!response.data.ok) {
        setErrorStatus("server_error");
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
  }, [queryKey, loadContext]);

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

  const fallbackProgress = [
    { key: "search", label: "Search", state: "completed" as const },
    { key: "results", label: "Results", state: "completed" as const },
    { key: "passenger_details", label: "Travelers", state: "current" as const },
    { key: "review", label: "Review", state: "upcoming" as const },
    { key: "payment", label: "Payment", state: "upcoming" as const },
  ];

  if (loading) {
    return (
      <BookingPageShell testId="passengers-loading">
        <BookingProgress steps={fallbackProgress} className="mb-6" />
        <BookingPageHeader title="Traveler information" description="Traveler information is loading." />
        <BookingLayout
          main={
            <BookingMainColumn>
              <div className="space-y-4" aria-busy="true" data-testid="passenger-skeleton">
                <p className="text-sm text-jp-muted" role="status">Traveler information is loading</p>
                <div className="h-40 animate-pulse rounded-jp-card border border-jp-border bg-jp-surface" />
                <div className="h-40 animate-pulse rounded-jp-card border border-jp-border bg-jp-surface" />
              </div>
            </BookingMainColumn>
          }
          sidebar={
            <BookingSidebar>
              <div className="h-48 animate-pulse rounded-jp-card border border-jp-border bg-jp-surface" data-testid="order-summary-skeleton" />
            </BookingSidebar>
          }
        />
      </BookingPageShell>
    );
  }

  if (errorStatus === "missing_session") {
    return <div className="mx-auto max-w-jp-booking p-8"><MissingBookingSessionState /></div>;
  }

  if (errorStatus === "offer_expired" || errorStatus === "checkout_expired") {
    return <div className="mx-auto max-w-jp-booking p-8"><OfferExpiredState redirectUrl={errorRedirect} /></div>;
  }

  if (errorStatus === "network_error") {
    return <div className="mx-auto max-w-jp-booking p-8"><NetworkErrorState onRetry={() => void loadContext()} /></div>;
  }

  if (errorStatus === "invalid_handoff") {
    return <div className="mx-auto max-w-jp-booking p-8"><InvalidHandoffState /></div>;
  }

  if (errorStatus === "supplier_requirements_unavailable") {
    return <div className="mx-auto max-w-jp-booking p-8"><SupplierRequirementsUnavailableState /></div>;
  }

  if (errorStatus === "server_error") {
    return <div className="mx-auto max-w-jp-booking p-8"><ServerErrorState onRetry={() => void loadContext()} /></div>;
  }

  if (expired) {
    return <div className="mx-auto max-w-jp-booking p-8"><BookingSessionExpiredState /></div>;
  }

  if (!context) {
    return <div className="mx-auto max-w-jp-booking p-8"><ServerErrorState onRetry={() => void loadContext()} /></div>;
  }

  const typeOrdinals: Record<string, number> = { adult: 0, child: 0, infant: 0 };

  const summarySidebar = (
    <OrderSummary itinerary={context.itinerary} travellerTotal={context.travellers.total} variant="flight-preview" testId="flight-preview" />
  );

  return (
    <BookingPageShell testId="passenger-details-page">
      <BookingProgress steps={context.booking_session.progress} className="mb-6" />

      <BookingPageHeader
        title="Traveler information"
        description="Enter details exactly as shown on travel documents."
        actions={
          <BookingSessionCountdown
            expiresAt={context.booking_session.expires_at}
            serverTime={context.booking_session.server_time}
            onExpired={() => setExpired(true)}
          />
        }
      />

      {context.validation_alert ? (
        <p className="mt-4 rounded-jp-md border border-amber-200 bg-amber-50 p-3 text-jp-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100" role="status">
          {context.validation_alert}
        </p>
      ) : null}

      <form
        onSubmit={(event) => void handleSubmit(event)}
        className="mt-6"
        data-testid="standard-passengers-form"
        noValidate
      >
        <BookingLayout
          mobileSummary={<MobileOrderSummary>{summarySidebar}</MobileOrderSummary>}
          main={
            <BookingMainColumn>
              {formError || Object.keys(fieldErrors).length > 0 ? (
                <div
                  ref={errorSummaryRef}
                  tabIndex={-1}
                  className="rounded-jp-md border border-red-200 bg-red-50 p-3 text-jp-sm text-red-800 dark:border-red-900 dark:bg-red-950/30 dark:text-red-100"
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
                className="hidden w-full sm:w-auto lg:inline-flex"
                disabled={submitting || expired}
                aria-busy={submitting}
                data-testid="save-and-continue"
              >
                {submitting ? "Saving…" : "Continue to review"}
              </PrimaryButton>
            </BookingMainColumn>
          }
          sidebar={<BookingSidebar>{summarySidebar}</BookingSidebar>}
        />

        <div className="mt-4 lg:hidden">
          <PrimaryButton
            type="submit"
            className="w-full"
            disabled={submitting || expired}
            aria-busy={submitting}
            data-testid="save-and-continue-mobile"
          >
            {submitting ? "Saving…" : "Continue to review"}
          </PrimaryButton>
        </div>
      </form>
    </BookingPageShell>
  );
}
