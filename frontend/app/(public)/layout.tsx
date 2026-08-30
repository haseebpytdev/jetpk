import type { ReactNode } from "react";
import { PublicShell } from "@/components/layout/PublicShell";
import { PublicConfigService, SeoJsonLd } from "@/features/public-content";
import { getPublicSession } from "@/services/session";

export const dynamic = "force-dynamic";

async function withTimeout<T>(promise: Promise<T>, ms: number, fallback: T): Promise<T> {
  let timer: ReturnType<typeof setTimeout> | undefined;
  try {
    return await Promise.race([
      promise,
      new Promise<T>((resolve) => {
        timer = setTimeout(() => resolve(fallback), ms);
      }),
    ]);
  } finally {
    if (timer) clearTimeout(timer);
  }
}

export default async function PublicGroupLayout({ children }: { children: ReactNode }) {
  const [session, config] = await Promise.all([
    // Soft-nav to Traveler must not stall 20–40s on session lock / Laravel bootstrap.
    withTimeout(getPublicSession(), 1500, { status: "anonymous" as const }),
    withTimeout(PublicConfigService.getConfig(), 2000, null),
  ]);

  return (
    <PublicShell
      session={session}
      branding={
        config
          ? {
              brand_name: config.brand_name,
              logo_url: config.logo_url,
              header_logo_height: config.header_logo_height,
            }
          : null
      }
    >
      <SeoJsonLd config={config} />
      {children}
    </PublicShell>
  );
}
