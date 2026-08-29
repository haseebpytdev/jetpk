/**
 * Book Now → Traveler timing marks (browser-only).
 * Emits CustomEvent `jp-book-now-timing` and stores last breakdown on window.
 */
export type BookNowTimingMark =
  | "T0_click"
  | "T1_handler"
  | "T2_revalidate_start"
  | "T3_revalidate_response"
  | "T4_draft_prep_start"
  | "T5_draft_prep_done"
  | "T6_nav_start"
  | "T7_passenger_route"
  | "T8_shell_visible"
  | "T9_field_enabled";

type TimingSession = {
  id: string;
  t0: number;
  marks: Partial<Record<BookNowTimingMark, number>>;
  deltasMs: Record<string, number | null>;
  meta?: Record<string, unknown>;
};

declare global {
  interface Window {
    __jpBookNowTiming?: TimingSession;
    __jpBookNowTimingLog?: TimingSession[];
  }
}

function ensureSession(reset = false): TimingSession | null {
  if (typeof window === "undefined") return null;
  if (!reset && window.__jpBookNowTiming) return window.__jpBookNowTiming;
  const session: TimingSession = {
    id: `bn-${Date.now().toString(36)}`,
    t0: performance.now(),
    marks: {},
    deltasMs: {},
  };
  window.__jpBookNowTiming = session;
  return session;
}

export function startBookNowTiming(meta?: Record<string, unknown>): string | null {
  const session = ensureSession(true);
  if (!session) return null;
  session.meta = meta;
  markBookNowTiming("T0_click");
  return session.id;
}

export function markBookNowTiming(mark: BookNowTimingMark, meta?: Record<string, unknown>): void {
  if (typeof window === "undefined") return;
  const session = ensureSession(false);
  if (!session) return;
  const now = performance.now();
  session.marks[mark] = now;
  if (meta) session.meta = { ...(session.meta ?? {}), ...meta };
  const fromT0 = Math.round(now - session.t0);
  session.deltasMs[`${mark}_from_T0`] = fromT0;
  try {
    window.dispatchEvent(new CustomEvent("jp-book-now-timing", { detail: { mark, fromT0, session } }));
  } catch {
    /* ignore */
  }
  if (mark === "T9_field_enabled" || mark === "T8_shell_visible") {
    window.__jpBookNowTimingLog = [...(window.__jpBookNowTimingLog ?? []), { ...session, marks: { ...session.marks } }];
  }
}

export function bookNowTimingSnapshot(): TimingSession | null {
  if (typeof window === "undefined") return null;
  return window.__jpBookNowTiming ? { ...window.__jpBookNowTiming, marks: { ...window.__jpBookNowTiming.marks } } : null;
}
