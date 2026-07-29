import type { StatusPresentation, SuccessPresentation } from "../types/review-payment";

const TONE_CLASSES: Record<SuccessPresentation["tone"], string> = {
  success: "border-emerald-200 bg-emerald-50 text-emerald-900",
  pending: "border-amber-200 bg-amber-50 text-amber-900",
  processing: "border-sky-200 bg-sky-50 text-sky-900",
  warning: "border-orange-200 bg-orange-50 text-orange-900",
  neutral: "border-jp-border bg-jp-surface-muted text-jp-text",
};

export function statusToneClass(tone: SuccessPresentation["tone"]): string {
  return TONE_CLASSES[tone] ?? TONE_CLASSES.neutral;
}

export function statusBadgeClass(status: StatusPresentation): string {
  const code = status.code.toLowerCase();
  if (code === "succeeded" || code === "confirmed" || code === "ticketed") {
    return "bg-emerald-100 text-emerald-800";
  }
  if (["pending", "processing", "not_started", "payment_pending"].includes(code)) {
    return "bg-amber-100 text-amber-800";
  }
  if (["failed", "cancelled", "rejected", "expired"].includes(code)) {
    return "bg-red-100 text-red-800";
  }
  return "bg-jp-surface-muted text-jp-muted";
}

export function prefersReducedMotion(): boolean {
  if (typeof window === "undefined") return true;
  return window.matchMedia("(prefers-reduced-motion: reduce)").matches;
}
