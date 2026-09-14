import { PublicShell } from "@/components/layout/PublicShell";
import { HomepageContent } from "@/features/home";
import { PublicConfigService, SeoJsonLd } from "@/features/public-content";
import { getPublicSession } from "@/services/session";

export default async function HomePage() {
  const session = await getPublicSession();
  const config = await PublicConfigService.getConfig();

  return (
    <PublicShell session={session}>
      <SeoJsonLd config={config} />
      <HomepageContent />
    </PublicShell>
  );
}
