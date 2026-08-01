"use client";

import { useEffect, useRef } from "react";

type ObserverCallback = (entry: IntersectionObserverEntry) => void;

const callbacks = new Map<Element, ObserverCallback>();
let observer: IntersectionObserver | null = null;

function getObserver(): IntersectionObserver {
  if (!observer) {
    observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          const callback = callbacks.get(entry.target);
          if (callback) callback(entry);
        });
      },
      { rootMargin: "0px 0px -8% 0px", threshold: 0.12 },
    );
  }
  return observer;
}

export function observeElement(element: Element, callback: ObserverCallback): () => void {
  callbacks.set(element, callback);
  getObserver().observe(element);
  return () => {
    callbacks.delete(element);
    getObserver().unobserve(element);
  };
}

export function useSharedIntersectionObserver(
  callback: ObserverCallback,
  enabled = true,
): React.RefObject<HTMLElement | null> {
  const ref = useRef<HTMLElement | null>(null);
  const callbackRef = useRef(callback);
  callbackRef.current = callback;

  useEffect(() => {
    const element = ref.current;
    if (!element || !enabled) return undefined;

    return observeElement(element, (entry) => callbackRef.current(entry));
  }, [enabled]);

  return ref;
}
