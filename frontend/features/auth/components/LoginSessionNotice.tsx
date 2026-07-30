"use client";

import { AuthStatusBanner } from "@/features/auth";

export function LoginSessionNotice({ reason }: { reason?: string }) {
  if (reason !== "session-expired") return null;
  return (
    <AuthStatusBanner
      tone="info"
      message="Your session has expired. Please sign in again to continue."
      live
    />
  );
}
