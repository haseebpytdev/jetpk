"use client";

/**
 * Single-flight public RSC prefetch coordinator.
 * Background queue is serial. Intent (hover/focus) may run in parallel so a
 * soft-nav click is not blocked behind an unrelated background Flight.
 */

type PrefetchFn = (href: string) => void | Promise<void>;

let prefetchImpl: PrefetchFn | null = null;
let backgroundBusy = false;
const backgroundQueue: string[] = [];
const warmed = new Set<string>();
const inflightIntent = new Set<string>();

function pumpBackground() {
  if (backgroundBusy || !prefetchImpl) return;

  const next = backgroundQueue.shift() ?? null;
  if (!next) return;
  if (warmed.has(next) || inflightIntent.has(next)) {
    queueMicrotask(pumpBackground);
    return;
  }

  backgroundBusy = true;
  Promise.resolve(prefetchImpl(next))
    .catch(() => {
      /* best-effort */
    })
    .finally(() => {
      warmed.add(next);
      backgroundBusy = false;
      pumpBackground();
    });
}

export function registerPublicPrefetchImpl(fn: PrefetchFn) {
  prefetchImpl = fn;
}

export function enqueueBackgroundPrefetch(href: string) {
  if (!href.startsWith("/") || warmed.has(href) || inflightIntent.has(href)) return;
  if (backgroundQueue.includes(href)) return;
  backgroundQueue.push(href);
  pumpBackground();
}

/** Hover/focus: start immediately (may overlap one background Flight). */
export function prefetchOnIntent(href: string) {
  if (!href.startsWith("/") || warmed.has(href) || inflightIntent.has(href)) return;
  if (!prefetchImpl) return;

  const idx = backgroundQueue.indexOf(href);
  if (idx >= 0) backgroundQueue.splice(idx, 1);

  inflightIntent.add(href);
  Promise.resolve(prefetchImpl(href))
    .catch(() => {
      /* best-effort */
    })
    .finally(() => {
      inflightIntent.delete(href);
      warmed.add(href);
      pumpBackground();
    });
}
