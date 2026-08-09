"use client";

import { ensureLaravelCsrfToken } from "@/lib/api";
import { useEffect } from "react";

/**
 * Primes the Laravel XSRF cookie before auth form submission on cold visits.
 */
export function AuthCsrfBootstrap() {
  useEffect(() => {
    void ensureLaravelCsrfToken();
  }, []);

  return null;
}
