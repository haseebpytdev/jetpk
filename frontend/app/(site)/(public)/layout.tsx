import type { ReactNode } from "react";
import type { Metadata } from "next";

/**
 * Soft-nav: zero-await pass-through. Chrome lives in parent `(site)/layout.tsx`.
 * Static metadata only — do not block Flight on PublicConfig.
 */
export const revalidate = 60;

export const metadata: Metadata = {
  robots: { index: true, follow: true },
};

export default function PublicGroupLayout({ children }: { children: ReactNode }) {
  return children;
}
