import { PublicShell } from "@/components/layout/PublicShell";
import { HomepageContent } from "@/features/home";
import { PublicConfigService } from "@/features/public-content/services/public-config-service";
import { isCmsPreviewFlag, readCmsPreviewToken } from "@/features/public-content/utils/cms-preview";
import { getPublicSession } from "@/services/session";

export const dynamic = "force-dynamic";

type HomePageProps = {
  searchParams?: Promise<Record<string, string | string[] | undefined>>;
};

export default async function HomePage({ searchParams }: HomePageProps) {
  const params = searchParams ? await searchParams : {};
  const preview = isCmsPreviewFlag(params.jp_preview);
  const previewToken = readCmsPreviewToken(params.jp_preview_token);
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
      <HomepageContent preview={preview} previewToken={previewToken} />
    </PublicShell>
  );
}
