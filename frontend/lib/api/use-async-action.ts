"use client";

import { useCallback, useRef, useState } from "react";
import type { ApiResult } from "./types";

export type AsyncActionState<T> = {
  status: "idle" | "pending" | "success" | "error";
  data: T | null;
  error: string | null;
  fieldErrors: Record<string, string>;
};

type UseAsyncActionOptions<TInput, TOutput> = {
  action: (input: TInput, signal: AbortSignal) => Promise<ApiResult<TOutput>>;
  mapFieldErrors?: (errors?: Record<string, string[]>) => Record<string, string>;
  onSuccess?: (data: TOutput) => void;
  onError?: (message: string) => void;
};

export function useAsyncAction<TInput, TOutput>({
  action,
  mapFieldErrors,
  onSuccess,
  onError,
}: UseAsyncActionOptions<TInput, TOutput>) {
  const [state, setState] = useState<AsyncActionState<TOutput>>({
    status: "idle",
    data: null,
    error: null,
    fieldErrors: {},
  });

  const requestIdRef = useRef(0);
  const abortRef = useRef<AbortController | null>(null);
  const pendingRef = useRef(false);

  const reset = useCallback(() => {
    abortRef.current?.abort();
    abortRef.current = null;
    pendingRef.current = false;
    setState({ status: "idle", data: null, error: null, fieldErrors: {} });
  }, []);

  const execute = useCallback(
    async (input: TInput): Promise<ApiResult<TOutput>> => {
      if (pendingRef.current) {
        return {
          ok: false,
          code: "unknown",
          status: 0,
          message: "Action already in progress.",
        };
      }

      pendingRef.current = true;
      abortRef.current?.abort();
      const controller = new AbortController();
      abortRef.current = controller;

      const requestId = ++requestIdRef.current;
      setState((prev) => ({
        ...prev,
        status: "pending",
        error: null,
        fieldErrors: {},
      }));

      const result = await action(input, controller.signal);

      if (requestId !== requestIdRef.current) {
        return result;
      }

      pendingRef.current = false;
      abortRef.current = null;

      if (result.ok) {
        setState({
          status: "success",
          data: result.data,
          error: null,
          fieldErrors: {},
        });
        onSuccess?.(result.data);
        return result;
      }

      const fieldErrors = mapFieldErrors?.(result.errors) ?? {};
      setState({
        status: "error",
        data: null,
        error: result.message,
        fieldErrors,
      });
      onError?.(result.message);
      return result;
    },
    [action, mapFieldErrors, onError, onSuccess],
  );

  const cancel = useCallback(() => {
    abortRef.current?.abort();
    abortRef.current = null;
    pendingRef.current = false;
    setState((prev) => ({ ...prev, status: prev.status === "pending" ? "idle" : prev.status }));
  }, []);

  return {
    ...state,
    isPending: state.status === "pending",
    execute,
    reset,
    cancel,
  };
}
