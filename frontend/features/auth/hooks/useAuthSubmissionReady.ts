"use client";

import { ensureLaravelCsrfToken } from "@/lib/api";
import { useEffect, useState } from "react";

type AuthSubmissionReadyState = {
  ready: boolean;
  csrfReady: boolean;
  error: string | null;
};

/**
 * Auth forms must not submit before client hydration and CSRF bootstrap complete.
 * Decorative page assets (illustrations, images) are not part of this gate.
 */
export function useAuthSubmissionReady(): AuthSubmissionReadyState {
  const [state, setState] = useState<AuthSubmissionReadyState>({
    ready: false,
    csrfReady: false,
    error: null,
  });

  useEffect(() => {
    let cancelled = false;

    void ensureLaravelCsrfToken()
      .then((token) => {
        if (cancelled) return;
        if (!token) {
          setState({
            ready: false,
            csrfReady: false,
            error: "Unable to prepare secure sign-in. Please refresh and try again.",
          });
          return;
        }

        setState({ ready: true, csrfReady: true, error: null });
      })
      .catch(() => {
        if (cancelled) return;
        setState({
          ready: false,
          csrfReady: false,
          error: "Network error. Check your connection and try again.",
        });
      });

    return () => {
      cancelled = true;
    };
  }, []);

  return state;
}
