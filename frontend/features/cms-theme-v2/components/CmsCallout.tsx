import Link from "next/link";
import type { CmsBlock } from "../lib/block-types";
import { validateCmsUrl, externalLinkRel } from "../lib/validate-cms-url";
import { PublicCallout } from "@/features/public-theme-v2";
import { CmsSection } from "./CmsSection";

type CmsCalloutProps = {
  block: Extract<CmsBlock, { type: "callout" }>;
};

export function CmsCallout({ block }: CmsCalloutProps) {
  const action = block.action ? validateCmsUrl(block.action.href) : null;

  return (
    <CmsSection width="narrow">
      <PublicCallout tone={block.tone ?? "info"} heading={block.heading} body={block.body} />
      {action?.ok && block.action ? (
        <p style={{ marginTop: "var(--jp-v2-space-md)" }}>
          {action.external ? (
            <a href={action.href} rel={externalLinkRel(true)} className="jp-v2-btn jp-v2-btn--secondary">
              {block.action.label}
            </a>
          ) : (
            <Link href={action.href} className="jp-v2-btn jp-v2-btn--secondary">
              {block.action.label}
            </Link>
          )}
        </p>
      ) : null}
    </CmsSection>
  );
}
