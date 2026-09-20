"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect } from "react";
import type { ComponentProps, MouseEvent, FocusEvent } from "react";
import {
  prefetchOnIntent,
  registerPublicPrefetchImpl,
} from "@/components/navigation/public-prefetch-coordinator";

type PrefetchOnIntentLinkProps = Omit<ComponentProps<typeof Link>, "prefetch"> & {
  /**
   * Soft-nav: allow Next viewport prefetch for always-visible header targets
   * (e.g. Groups / Login) so the 600ms homepage settle can warm RSC before click.
   * Default remains intent-only to avoid footer/CMS stampede.
   */
  priorityPrefetch?: boolean;
};

/**
 * Soft-nav: no mount/viewport RSC stampede (`prefetch={false}`) by default.
 * Warm on hover/focus/pointerdown via the shared single-flight coordinator.
 */
export function PrefetchOnIntentLink({
  href,
  onMouseEnter,
  onFocus,
  priorityPrefetch = false,
  ...rest
}: PrefetchOnIntentLinkProps) {
  const router = useRouter();

  useEffect(() => {
    registerPublicPrefetchImpl((path) => {
      void router.prefetch(path);
    });
  }, [router]);

  const warm = () => {
    try {
      if (typeof href === "string" && href.startsWith("/")) {
        prefetchOnIntent(href);
      }
    } catch {
      /* best-effort */
    }
  };

  return (
    <Link
      {...rest}
      href={href}
      prefetch={priorityPrefetch ? true : false}
      onPointerDown={() => {
        warm();
      }}
      onMouseEnter={(event: MouseEvent<HTMLAnchorElement>) => {
        warm();
        onMouseEnter?.(event);
      }}
      onFocus={(event: FocusEvent<HTMLAnchorElement>) => {
        warm();
        onFocus?.(event);
      }}
    />
  );
}
