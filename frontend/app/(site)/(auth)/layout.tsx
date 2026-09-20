import type { ReactNode } from "react";

/**
 * Soft-nav: pass-through only. Shared `(site)` PublicShell persists across
 * home → /login so auth must not remount chrome or await PublicConfig.
 */
export const revalidate = 60;

export default function AuthGroupLayout({ children }: { children: ReactNode }) {
  return children;
}
