"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import { fetchConfirmation, fetchPaymentStatus } from "../services/booking-checkout-api";
import type { BookingConfirmation, PaymentStatusResponse, PollConfig } from "../types/review-payment";

type PollMode = "confirmation" | "payment";

type UseBookingStatusPollOptions = {
  mode: PollMode;
  reference?: string;
  enabled?: boolean;
};

const DEFAULT_MAX_DURATION_MS = 180_000;

export function useBookingStatusPoll({ mode, reference, enabled = true }: UseBookingStatusPollOptions) {
  const [data, setData] = useState<BookingConfirmation | PaymentStatusResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [polling, setPolling] = useState(false);
  const [timedOut, setTimedOut] = useState(false);

  const attemptsRef = useRef(0);
  const timerRef = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);
  const startedAtRef = useRef<number | null>(null);
  const inFlightRef = useRef(false);
  const cancelledRef = useRef(false);

  const resolvePollConfig = useCallback((payload: BookingConfirmation | PaymentStatusResponse): PollConfig | null => {
    if (mode === "payment") {
      const paymentPayload = payload as PaymentStatusResponse;
      return paymentPayload.booking_poll?.should_poll
        ? paymentPayload.booking_poll
        : paymentPayload.poll ?? null;
    }

    return (payload as BookingConfirmation).poll ?? null;
  }, [mode]);

  const shouldStopPolling = useCallback((payload: BookingConfirmation | PaymentStatusResponse): boolean => {
    const config = resolvePollConfig(payload);
    return config ? !config.should_poll : true;
  }, [resolvePollConfig]);

  const clearTimer = useCallback(() => {
    if (timerRef.current) {
      clearTimeout(timerRef.current);
      timerRef.current = undefined;
    }
  }, []);

  const load = useCallback(async () => {
    if (inFlightRef.current) return null;
    inFlightRef.current = true;

    const response = mode === "payment" ? await fetchPaymentStatus(reference) : await fetchConfirmation();
    inFlightRef.current = false;
    setLoading(false);

    if (!response.ok) {
      setError(response.message);
      return null;
    }

    setData(response.data);
    setError(null);
    return response.data;
  }, [mode, reference]);

  const reload = useCallback(async () => {
    setLoading(true);
    setError(null);
    setTimedOut(false);
    attemptsRef.current = 0;
    startedAtRef.current = Date.now();
    return load();
  }, [load]);

  useEffect(() => {
    if (!enabled) return undefined;

    cancelledRef.current = false;
    attemptsRef.current = 0;
    startedAtRef.current = Date.now();
    setTimedOut(false);
    setPolling(false);

    const schedulePoll = (config: PollConfig) => {
      clearTimer();
      setPolling(true);

      timerRef.current = setTimeout(async () => {
        if (cancelledRef.current) return;

        if (document.hidden) {
          schedulePoll(config);
          return;
        }

        const elapsed = Date.now() - (startedAtRef.current ?? Date.now());
        if (elapsed >= DEFAULT_MAX_DURATION_MS) {
          setPolling(false);
          setTimedOut(true);
          setError("Payment status is taking longer than expected. You can refresh to check again.");
          return;
        }

        attemptsRef.current += 1;
        const nextPayload = await load();
        if (cancelledRef.current || !nextPayload) {
          setPolling(false);
          return;
        }

        if (!shouldStopPolling(nextPayload) && attemptsRef.current < config.max_attempts) {
          schedulePoll(config);
          return;
        }

        setPolling(false);
      }, config.interval_ms);
    };

    void (async () => {
      const payload = await load();
      if (cancelledRef.current || !payload) return;

      const config = resolvePollConfig(payload);
      if (!config || shouldStopPolling(payload) || attemptsRef.current >= config.max_attempts) {
        setPolling(false);
        return;
      }

      schedulePoll(config);
    })();

    const onVisibility = () => {
      if (!document.hidden && !cancelledRef.current) {
        void load();
      }
    };

    document.addEventListener("visibilitychange", onVisibility);

    return () => {
      cancelledRef.current = true;
      clearTimer();
      setPolling(false);
      document.removeEventListener("visibilitychange", onVisibility);
    };
  }, [clearTimer, enabled, load, resolvePollConfig, shouldStopPolling]);

  return { data, loading, error, polling, timedOut, reload };
}
