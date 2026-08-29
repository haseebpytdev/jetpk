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
import { Dialog } from "@/components/ui/Dialog";
import { PrimaryButton } from "@/components/ui/PrimaryButton";
import { SecondaryButton } from "@/components/ui/SecondaryButton";
import { mapFieldErrors, ensureLaravelCsrfToken } from "@/features/auth/utils/laravel-auth-api";
import { fetchStandardPassengersContext, submitStandardPassengers } from "../services/standard-booking-api";
import type { ContactFormValues, PassengerFormValues, StandardPassengersContext } from "../types";
import {
  buildContactFromContext,
  buildPassengerFormData,
  buildPassengersFromContext,
  passengerLabel,
} from "../utils/passenger-form";
import { isAllowedBookingNextUrl, resolveBookingNextUrl } from "../utils/allowlist";
import { laravelApiPath } from "@/services/flight-search";
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
import { FareChangeDialog } from "@/features/flight-details/components/FareChangeDialog";
import { revalidateOffer } from "@/features/flight-results/services/flight-results-api";
import { markBookNowTiming } from "@/features/flight-results/utils/book-now-timing";

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
  const [termsAccepted, setTermsAccepted] = useState(false);
  const [changeFlightOpen, setChangeFlightOpen] = useState(false);
  const [changeFlightBusy, setChangeFlightBusy] = useState(false);
  const [errorStatus, setErrorStatus] = useState<string | null>(null);
  const [errorRedirect, setErrorRedirect] = useState<string | null>(null);
  const [fareChange, setFareChange] = useState<{
    originalTotal?: number;
    confirmedTotal?: number;
    currency?: string;
  } | null>(null);
  const [fareChangeBusy, setFareChangeBusy] = useState(false);
  const submitLock = useRef(false);
  const errorSummaryRef = useRef<HTMLDivElement>(null);
  const autoRevalidateAttempted = useRef(false);
  const shellMarkedRef = useRef(false);
  const fieldMarkedRef = useRef(false);

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
        const data = response.data as { status?: string; redirect_url?: string; register_url?: string } | undefined;
        const apiStatus = (data?.status ?? "").toLowerCase();
        if (response.status === 401 || apiStatus === "guest_booking_disabled") {
          const authRedirect = data?.redirect_url ?? `/login?redirect=${encodeURIComponent(window.location.pathname + window.location.search)}`;
          window.location.assign(authRedirect);
          return;
        }
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

  // Book Now timing: traveler shell first paint (T8) and first editable field ready (T9).
  useEffect(() => {
    if (!loading || shellMarkedRef.current) return;
    shellMarkedRef.current = true;
    markBookNowTiming("T8_shell_visible", { phase: "passengers_loading" });
  }, [loading]);

  useEffect(() => {
    if (loading || !context || fareChange || errorStatus || expired || fieldMarkedRef.current) return;
    fieldMarkedRef.current = true;
    markBookNowTiming("T9_field_enabled", { phase: "passengers_form_ready" });
  }, [loading, context, fareChange, errorStatus, expired]);

  // Silent automatic reprice when checkout still carries an approximate estimate.
  useEffect(() => {
    if (!context || loading || fareChange) return;
    if (autoRevalidateAttempted.current) return;
    if (!context.itinerary.price_needs_refresh && !context.itinerary.price_is_approximate) return;

    const searchId = context.selection.search_id?.trim();
    const offerId = context.selection.offer_id?.trim();
    if (!searchId || !offerId) return;

    autoRevalidateAttempted.current = true;
    let cancelled = false;

    void (async () => {
      const result = await revalidateOffer({
        searchId,
        offerId,
        selectedFareOptionId: context.selection.fare_option_key || undefined,
        acceptFareChange: false,
      });
      if (cancelled) return;

      if (!result.ok) {
        const apiStatus = (result.data?.status ?? "").toLowerCase();
        if (result.status === 410 || apiStatus.includes("unavailable") || apiStatus.includes("expired")) {
          setErrorStatus("offer_expired");
          setFormError("The selected fare is no longer available. Please choose another fare.");
        }
        return;
      }

      const revalidation = result.data.revalidation ?? {};
      const priceChanged =
        result.data.status === "fare_changed" ||
        result.data.requires_fare_change_acceptance === true ||
        Boolean(revalidation.price_changed);

      if (priceChanged) {
        setFareChange({
          originalTotal:
            typeof revalidation.original_total === "number" ? revalidation.original_total : undefined,
          confirmedTotal:
            typeof revalidation.confirmed_total === "number" ? revalidation.confirmed_total : undefined,
          currency: typeof revalidation.currency === "string" ? revalidation.currency : "PKR",
        });
        return;
      }

      // Unchanged price — reload context so authoritative totals clear the refresh banner.
      const refreshed = await loadContext();
      if (cancelled || !refreshed.ok || !refreshed.data.ok) return;
      setContext(refreshed.data);
      setLoading(false);
    })();

    return () => {
      cancelled = true;
    };
  }, [context, fareChange, loadContext, loading]);

  const acceptPassengerFareChange = useCallback(async () => {
    if (!context || fareChangeBusy) return;
    const searchId = context.selection.search_id?.trim();
    const offerId = context.selection.offer_id?.trim();
    if (!searchId || !offerId) return;

    setFareChangeBusy(true);
    try {
      const result = await revalidateOffer({
        searchId,
        offerId,
        selectedFareOptionId: context.selection.fare_option_key || undefined,
        acceptFareChange: true,
      });
      if (!result.ok) {
        setFormError(result.message || "Unable to accept the updated fare. Please search again.");
        setFareChange(null);
        return;
      }
      setFareChange(null);
      const refreshed = await loadContext();
      if (refreshed.ok && refreshed.data.ok) {
        setContext(refreshed.data);
        setPassengers(buildPassengersFromContext(refreshed.data));
        setContact(buildContactFromContext(refreshed.data));
      }
    } finally {
      setFareChangeBusy(false);
    }
  }, [context, fareChangeBusy, loadContext]);

  const updatePassenger = useCallback((index: number, field: keyof PassengerFormValues, value: string) => {
    setPassengers((rows) => rows.map((row, i) => (i === index ? { ...row, [field]: value } : row)));
  }, []);

  const replacePassenger = useCallback((index: number, next: PassengerFormValues) => {
    setPassengers((rows) => rows.map((row, i) => (i === index ? next : row)));
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

    if (!termsAccepted) {
      setFormError("Please confirm the traveler information and accept the Terms & Conditions and Privacy Policy to continue.");
      setFieldErrors({ terms_accepted: "Required" });
      errorSummaryRef.current?.focus();
      return;
    }

    submitLock.current = true;
    setSubmitting(true);
    setFieldErrors({});
    setFormError(null);

    const formData = buildPassengerFormData(context, passengers, contact, { termsAccepted: true });
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

  const formHasPassengerOrContactData = useCallback(() => {
    const passengerFilled = passengers.some((passenger) =>
      [passenger.first_name, passenger.last_name, passenger.passport_number, passenger.date_of_birth].some(
        (value) => value.trim() !== "",
      ),
    );
    const contactFilled = [contact.email, contact.phone_number, contact.contact_name].some(
      (value) => value.trim() !== "",
    );
    return passengerFilled || contactFilled;
  }, [contact, passengers]);

  const executeChangeFlight = useCallback(async () => {
    if (!context) return;
    if (context.change_flight && context.change_flight.safe === false) {
      setFormError("This booking already has a supplier hold. Changing the flight requires the authorized booking lifecycle.");
      setChangeFlightOpen(false);
      return;
    }

    const abandonUrl = context.change_flight?.abandon_url ?? "/booking/abandon-selected-offer";
    const csrf = await ensureLaravelCsrfToken();
    setChangeFlightBusy(true);
    try {
      const response = await fetch(laravelApiPath(`${abandonUrl}?format=json`), {
        method: "POST",
        credentials: "include",
        headers: {
          Accept: "application/json",
          "X-Requested-With": "XMLHttpRequest",
          ...(csrf ? { "X-XSRF-TOKEN": csrf } : {}),
        },
      });
      const body = (await response.json().catch(() => null)) as {
        ok?: boolean;
        results_url?: string;
        message?: string;
        status?: string;
        fresh_search?: boolean;
      } | null;
      if (!response.ok || !body?.ok) {
        setFormError(body?.message ?? "Unable to change flight right now.");
        setChangeFlightBusy(false);
        return;
      }
      const next = body.results_url || context.change_flight?.results_url || context.booking_session.previous_url || "/flights/results";
      const resolved = next.startsWith("http") ? next : next.startsWith("/") ? next : `/${next}`;
      window.location.assign(resolved);
    } catch {
      setFormError("Unable to change flight right now.");
      setChangeFlightBusy(false);
    }
  }, [context]);

  const handleChangeFlight = useCallback(() => {
    if (!context) return;
    if (context.change_flight && context.change_flight.safe === false) {
      setFormError("This booking already has a supplier hold. Changing the flight requires the authorized booking lifecycle.");
      return;
    }
    if (formHasPassengerOrContactData()) {
      setChangeFlightOpen(true);
      return;
    }
    void executeChangeFlight();
  }, [context, executeChangeFlight, formHasPassengerOrContactData]);

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
        <BookingPageHeader title="Traveler information" description="Preparing your trip…" />
        <BookingLayout
          main={
            <BookingMainColumn>
              <div className="space-y-4" aria-busy="true" data-testid="passenger-skeleton">
                <div className="min-h-[10rem] rounded-jp-card border border-jp-border bg-jp-surface p-4">
                  <div className="h-4 w-28 animate-pulse rounded bg-jp-surface-muted" />
                  <div className="mt-4 grid gap-3 sm:grid-cols-2">
                    <div className="h-10 animate-pulse rounded-jp-md bg-jp-surface-muted" />
                    <div className="h-10 animate-pulse rounded-jp-md bg-jp-surface-muted" />
                    <div className="h-10 animate-pulse rounded-jp-md bg-jp-surface-muted" />
                    <div className="h-10 animate-pulse rounded-jp-md bg-jp-surface-muted" />
                  </div>
                </div>
              </div>
            </BookingMainColumn>
          }
          sidebar={
            <BookingSidebar>
              <div className="min-h-[12rem] rounded-jp-card border border-jp-border bg-jp-surface p-4" data-testid="order-summary-skeleton">
                <div className="h-4 w-24 animate-pulse rounded bg-jp-surface-muted" />
                <div className="mt-3 h-16 animate-pulse rounded-jp-md bg-jp-surface-muted" />
              </div>
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
    <OrderSummary
      itinerary={context.itinerary}
      travellerTotal={context.travellers.total}
      variant="flight-preview"
      testId="flight-preview"
      onChangeFlight={() => handleChangeFlight()}
      changeFlightDisabled={context.change_flight?.safe === false}
    />
  );

  return (
    <BookingPageShell testId="passenger-details-page">
      <FareChangeDialog
        open={fareChange !== null}
        originalTotal={fareChange?.originalTotal}
        confirmedTotal={fareChange?.confirmedTotal}
        currency={fareChange?.currency}
        loading={fareChangeBusy}
        onAccept={() => void acceptPassengerFareChange()}
        onCancel={() => {
          setFareChange(null);
          setFormError("The selected fare is no longer available at the previous price. Please choose another fare.");
        }}
      />
      <Dialog
        open={changeFlightOpen}
        onClose={() => {
          if (!changeFlightBusy) setChangeFlightOpen(false);
        }}
        title="Change your flight?"
        description="The traveler details you've entered on this page will be cleared. Your route, dates and passenger search will be kept."
        footer={
          <>
            <SecondaryButton
              type="button"
              data-testid="change-flight-keep"
              disabled={changeFlightBusy}
              onClick={() => setChangeFlightOpen(false)}
            >
              Keep this flight
            </SecondaryButton>
            <PrimaryButton
              type="button"
              data-testid="change-flight-search-other"
              disabled={changeFlightBusy}
              aria-busy={changeFlightBusy}
              onClick={() => void executeChangeFlight()}
            >
              Search other flights
            </PrimaryButton>
          </>
        }
      >
        <p className="text-sm text-jp-muted" data-testid="change-flight-confirm-copy">
          A fresh flight search will run with your current route, dates, cabin and passenger counts. Your previous fare selection will not be kept.
        </p>
      </Dialog>

      <BookingProgress steps={context.booking_session.progress} className="mb-6" />

      <BookingPageHeader
        title="Traveler information"
        description="Enter details exactly as shown on the passport."
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
          mobileSummary={
            <MobileOrderSummary label="Flight preview">{summarySidebar}</MobileOrderSummary>
          }
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
                    onReplacePassenger={replacePassenger}
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

              <label
                className="flex items-start gap-3 rounded-jp-lg border border-jp-border bg-jp-surface p-4 text-sm text-jp-text shadow-jp-card"
                data-testid="terms-acceptance"
              >
                <input
                  type="checkbox"
                  className="mt-1 h-4 w-4 rounded border-jp-border text-jp-primary focus-visible:shadow-jp-focus"
                  checked={termsAccepted}
                  onChange={(event) => setTermsAccepted(event.target.checked)}
                  data-testid="terms-acceptance-checkbox"
                  aria-invalid={Boolean(fieldErrors.terms_accepted)}
                />
                <span>
                  I confirm that the traveler and document information provided is accurate, and I agree to JetPakistan&apos;s{" "}
                  <a href={context.consent?.terms_url ?? "/terms"} className="font-semibold text-jp-primary underline" target="_blank" rel="noreferrer">
                    Terms &amp; Conditions
                  </a>{" "}
                  and{" "}
                  <a href={context.consent?.privacy_url ?? "/privacy"} className="font-semibold text-jp-primary underline" target="_blank" rel="noreferrer">
                    Privacy Policy
                  </a>
                  , including the applicable airline/supplier fare rules, change, cancellation and refund conditions.
                </span>
              </label>

              <PrimaryButton
                type="submit"
                className="hidden w-full sm:w-auto lg:inline-flex"
                disabled={submitting || expired || !termsAccepted}
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
            disabled={submitting || expired || !termsAccepted}
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
