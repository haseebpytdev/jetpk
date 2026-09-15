import type { PublicConfig } from "../services/public-config-service";

type SiteVerificationMetaProps = {
  config: PublicConfig | null;
};

export function SiteVerificationMeta({ config }: SiteVerificationMetaProps) {
  const google = config?.site_verification?.google?.trim();
  const bing = config?.site_verification?.bing?.trim();

  return (
    <>
      {google ? <meta name="google-site-verification" content={google} /> : null}
      {bing ? <meta name="msvalidate.01" content={bing} /> : null}
    </>
  );
}
