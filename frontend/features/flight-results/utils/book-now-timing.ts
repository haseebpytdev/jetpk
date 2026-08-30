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

export type PassengersServerTiming = {
  correlation_id?: string;
  total_ms?: number | null;
  session_hydrate_ms?: number | null;
  offer_resolve_ms?: number | null;
  passenger_contact_load_ms?: number | null;
  S0_ms?: number | null;
  S7_ms?: number | null;
  S8_ms?: number | null;
};

export type ClientHydrationTiming = {
  N0_page_start_ms?: number | null;
  N1_fetch_start_ms?: number | null;
  N2_fetch_end_ms?: number | null;
  N3_form_render_ms?: number | null;
  N4_hydration_settled_ms?: number | null;
};

type TimingSession = {
  id: string;
  t0: number;
  marks: Partial<Record<BookNowTimingMark, number>>;
  deltasMs: Record<string, number | null>;
  meta?: Record<string, unknown>;
  serverTiming?: PassengersServerTiming;
  clientHydration?: ClientHydrationTiming;
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

/** Restore timing session after hard navigation to Traveler. */
export function restoreBookNowTimingFromStorage(): TimingSession | null {
  if (typeof window === "undefined") return null;
  try {
    const raw = sessionStorage.getItem("jp-book-now-timing");
    if (!raw) return null;
    sessionStorage.removeItem("jp-book-now-timing");
    const parsed = JSON.parse(raw) as TimingSession;
    if (!parsed?.id || typeof parsed.t0 !== "number") return null;
    // Re-base marks onto a fresh performance.now() timeline while preserving
    // from_T0 deltas recorded before the hard navigation.
    const now = performance.now();
    const session: TimingSession = {
      ...parsed,
      t0: now - (typeof parsed.deltasMs?.T7_passenger_route_from_T0 === "number"
        ? parsed.deltasMs.T7_passenger_route_from_T0
        : typeof parsed.deltasMs?.T6_nav_start_from_T0 === "number"
          ? parsed.deltasMs.T6_nav_start_from_T0
          : 0),
      marks: { ...(parsed.marks ?? {}) },
      deltasMs: { ...(parsed.deltasMs ?? {}) },
    };
    window.__jpBookNowTiming = session;
    return session;
  } catch {
    return null;
  }
}

export function markBookNowTiming(mark: BookNowTimingMark, meta?: Record<string, unknown>): void {
  if (typeof window === "undefined") return;
  const session = ensureSession(false);
  if (!session) return;
  // Keep the earliest shell mark (loading.tsx may fire before page mount).
  if (mark === "T8_shell_visible" && session.marks.T8_shell_visible != null) {
    if (meta) session.meta = { ...(session.meta ?? {}), ...meta };
    return;
  }
  if (mark === "T9_field_enabled" && session.marks.T9_field_enabled != null) {
    if (meta) session.meta = { ...(session.meta ?? {}), ...meta };
    return;
  }
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
  return window.__jpBookNowTiming
    ? {
        ...window.__jpBookNowTiming,
        marks: { ...window.__jpBookNowTiming.marks },
        serverTiming: window.__jpBookNowTiming.serverTiming
          ? { ...window.__jpBookNowTiming.serverTiming }
          : undefined,
        clientHydration: window.__jpBookNowTiming.clientHydration
          ? { ...window.__jpBookNowTiming.clientHydration }
          : undefined,
      }
    : null;
}

/** Attach non-PII Laravel passengers Server-Timing / X-JP-Passengers-Timing. */
export function attachPassengersServerTiming(timing: PassengersServerTiming): void {
  if (typeof window === "undefined") return;
  const session = ensureSession(false);
  if (!session) return;
  session.serverTiming = { ...(session.serverTiming ?? {}), ...timing };
  session.meta = {
    ...(session.meta ?? {}),
    server_passengers_total_ms: timing.total_ms ?? null,
    session_hydrate_ms: timing.session_hydrate_ms ?? null,
    offer_resolve_ms: timing.offer_resolve_ms ?? null,
  };
}

export function markClientHydration(
  key: keyof ClientHydrationTiming,
  fromT0?: number,
): void {
  if (typeof window === "undefined") return;
  const session = ensureSession(false);
  if (!session) return;
  const value =
    typeof fromT0 === "number" ? fromT0 : Math.round(performance.now() - session.t0);
  session.clientHydration = { ...(session.clientHydration ?? {}), [key]: value };
}
