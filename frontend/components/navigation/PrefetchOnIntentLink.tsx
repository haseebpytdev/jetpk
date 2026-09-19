"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect } from "react";
import type { ComponentProps, MouseEvent, FocusEvent } from "react";
import {
  prefetchOnIntent,
  registerPublicPrefetchImpl,
} from "@/components/navigation/public-prefetch-coordinator";

type PrefetchOnIntentLinkProps = Omit<ComponentProps<typeof Link>, "prefetch">;

/**
 * Soft-nav: no mount/viewport RSC stampede (`prefetch={false}`).
 * Warm only on hover/focus intent via the shared single-flight coordinator.
 */
export function PrefetchOnIntentLink({
  href,
  onMouseEnter,
  onFocus,
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
      prefetch={false}
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
