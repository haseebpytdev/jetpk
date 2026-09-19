"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import type { ComponentProps, MouseEvent, FocusEvent } from "react";

type PrefetchOnIntentLinkProps = Omit<ComponentProps<typeof Link>, "prefetch">;

/**
 * Soft-nav: no mount/viewport RSC stampede (`prefetch={false}`).
 * Warm only on hover/focus intent (matches real users + cert harness hover-before-click).
 */
export function PrefetchOnIntentLink({
  href,
  onMouseEnter,
  onFocus,
  ...rest
}: PrefetchOnIntentLinkProps) {
  const router = useRouter();

  const warm = () => {
    try {
      if (typeof href === "string" && href.startsWith("/")) {
        void router.prefetch(href);
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
