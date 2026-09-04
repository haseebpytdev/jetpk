import { PublicShell } from "@/components/layout/PublicShell";
import { HomepageContent } from "@/features/home";
import { PublicConfigService } from "@/features/public-content/services/public-config-service";
import { readCmsPreviewToken } from "@/features/public-content/utils/cms-preview";
import { getPublicSession } from "@/services/session";

export const dynamic = "force-dynamic";

type HomePreviewPageProps = {
  searchParams?: Promise<Record<string, string | string[] | undefined>>;
};

/** CMS draft preview for homepage — kept off the published `/` soft-nav path. */
export default async function HomePreviewPage({ searchParams }: HomePreviewPageProps) {
  const params = searchParams ? await searchParams : {};
  const previewToken = readCmsPreviewToken(params.jp_preview_token);
  const [session, config] = await Promise.all([
    getPublicSession(),
    PublicConfigService.getConfig(),
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
      aiEnabled={Boolean(config?.ai_assistant_enabled)}
    >
      <HomepageContent preview previewToken={previewToken} />
    </PublicShell>
  );
}
