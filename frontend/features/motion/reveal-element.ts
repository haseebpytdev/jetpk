export const REVEAL_FAILSAFE_MS = 600;

export function revealElement(element: HTMLElement): void {
  element.classList.add("jp-reveal-visible");
  element.setAttribute("data-revealed", "true");
}

export function armRevealElement(element: HTMLElement): void {
  element.classList.add("jp-scroll-reveal--armed");
}

function isInViewport(element: HTMLElement): boolean {
  const rect = element.getBoundingClientRect();
  return rect.top < window.innerHeight && rect.bottom > 0;
}

/**
 * Observe a reveal target with a shared IntersectionObserver and bounded failsafe.
 * Content stays visible until armed; unrevealed in-viewport targets are forced visible
 * if no observer decision arrives within REVEAL_FAILSAFE_MS.
 */
export function observeRevealElement(
  element: HTMLElement,
  options: { reduced?: boolean } = {},
): () => void {
  if (options.reduced) {
    revealElement(element);
    return () => undefined;
  }

  if (typeof IntersectionObserver === "undefined") {
    revealElement(element);
    return () => undefined;
  }

  armRevealElement(element);

  let cancelled = false;
  let failsafeTimer: ReturnType<typeof setTimeout> | undefined;
  let observer: IntersectionObserver | null = null;

  const finish = () => {
    if (cancelled || element.getAttribute("data-revealed") === "true") return;
    revealElement(element);
    if (failsafeTimer) clearTimeout(failsafeTimer);
    observer?.unobserve(element);
  };

  try {
    observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) finish();
        });
      },
      { rootMargin: "0px 0px -8% 0px", threshold: 0.12 },
    );
    observer.observe(element);
  } catch {
    finish();
    return () => undefined;
  }

  failsafeTimer = setTimeout(() => {
    if (cancelled || element.getAttribute("data-revealed") === "true") return;
    if (isInViewport(element)) finish();
  }, REVEAL_FAILSAFE_MS);

  return () => {
    cancelled = true;
    if (failsafeTimer) clearTimeout(failsafeTimer);
    observer?.disconnect();
  };
}
