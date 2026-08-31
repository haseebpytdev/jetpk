import type { ReactNode } from "react";
import { PublicShell } from "@/components/layout/PublicShell";
import { PublicConfigService } from "@/features/public-content/services/public-config-service";
import { getPublicSession } from "@/services/session";

export default async function FlightsLayout({ children }: { children: ReactNode }) {
  const [session, config] = await Promise.all([
    getPublicSession(),
    PublicConfigService.getConfig(),
  ]);

  return (
    <PublicShell session={session} aiEnabled={Boolean(config?.ai_assistant_enabled)}>
      {children}
    </PublicShell>
  );
}
