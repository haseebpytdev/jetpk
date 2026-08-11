import { PublicShell } from "@/components/layout/PublicShell";
import { HomepageContent } from "@/features/home";
import { PublicConfigService } from "@/features/public-content/services/public-config-service";
import { getPublicSession } from "@/services/session";

export const dynamic = "force-dynamic";

export default async function HomePage() {
  const session = await getPublicSession();
  const config = await PublicConfigService.getConfig();

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
      <HomepageContent />
    </PublicShell>
  );
}
