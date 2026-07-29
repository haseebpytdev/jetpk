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

export function useBookingStatusPoll({ mode, reference, enabled = true }: UseBookingStatusPollOptions) {
  const [data, setData] = useState<BookingConfirmation | PaymentStatusResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const attemptsRef = useRef(0);
  const timerRef = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);

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

  const load = useCallback(async () => {
    const response = mode === "payment" ? await fetchPaymentStatus(reference) : await fetchConfirmation();
    setLoading(false);

    if (!response.ok) {
      setError(response.message);
      return null;
    }

    setData(response.data);
    setError(null);
    return response.data;
  }, [mode, reference]);

  useEffect(() => {
    if (!enabled) return undefined;

    let cancelled = false;
    attemptsRef.current = 0;

    const schedulePoll = (config: PollConfig) => {
      timerRef.current = setTimeout(async () => {
        if (cancelled || document.hidden) {
          schedulePoll(config);
          return;
        }

        attemptsRef.current += 1;
        const nextPayload = await load();
        if (cancelled || !nextPayload) return;

        if (!shouldStopPolling(nextPayload) && attemptsRef.current < config.max_attempts) {
          schedulePoll(config);
        }
      }, config.interval_ms);
    };

    void (async () => {
      const payload = await load();
      if (cancelled || !payload) return;

      const config = resolvePollConfig(payload);
      if (!config || shouldStopPolling(payload) || attemptsRef.current >= config.max_attempts) {
        return;
      }

      schedulePoll(config);
    })();

    const onVisibility = () => {
      if (!document.hidden) {
        void load();
      }
    };

    document.addEventListener("visibilitychange", onVisibility);

    return () => {
      cancelled = true;
      if (timerRef.current) clearTimeout(timerRef.current);
      document.removeEventListener("visibilitychange", onVisibility);
    };
  }, [enabled, load, resolvePollConfig, shouldStopPolling]);

  return { data, loading, error, reload: load };
}
