"use client";

/**
 * Single-flight public RSC prefetch coordinator.
 * Intent (hover/focus) preempts the background queue so soft-nav clicks
 * do not compete with stampeding privacy/faq/terms/support Flights.
 */

type PrefetchFn = (href: string) => void | Promise<void>;

let prefetchImpl: PrefetchFn | null = null;
let busy = false;
let intentHref: string | null = null;
const backgroundQueue: string[] = [];
const warmed = new Set<string>();

function pump() {
  if (busy || !prefetchImpl) return;

  const next = intentHref ?? backgroundQueue.shift() ?? null;
  if (!next) return;
  if (intentHref === next) intentHref = null;
  if (warmed.has(next)) {
    queueMicrotask(pump);
    return;
  }

  busy = true;
  Promise.resolve(prefetchImpl(next))
    .catch(() => {
      /* best-effort */
    })
    .finally(() => {
      warmed.add(next);
      busy = false;
      pump();
    });
}

export function registerPublicPrefetchImpl(fn: PrefetchFn) {
  prefetchImpl = fn;
}

export function enqueueBackgroundPrefetch(href: string) {
  if (!href.startsWith("/") || warmed.has(href)) return;
  if (backgroundQueue.includes(href) || intentHref === href) return;
  backgroundQueue.push(href);
  pump();
}

/** Hover/focus: jump the line; do not wait for the full background queue. */
export function prefetchOnIntent(href: string) {
  if (!href.startsWith("/") || warmed.has(href)) return;
  intentHref = href;
  // Drop duplicate from background if present.
  const idx = backgroundQueue.indexOf(href);
  if (idx >= 0) backgroundQueue.splice(idx, 1);
  pump();
}
