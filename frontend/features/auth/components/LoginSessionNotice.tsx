"use client";

import { useSearchParams } from "next/navigation";
import { AuthStatusBanner } from "@/features/auth";

export function LoginSessionNotice({ reason: reasonProp }: { reason?: string }) {
  const params = useSearchParams();
  const reason = reasonProp ?? params.get("reason") ?? undefined;
  if (reason !== "session-expired") return null;
  return (
    <AuthStatusBanner
      tone="info"
      message="Your session has expired. Please sign in again to continue."
      live
    />
  );
}
