import Link from "next/link";
import type { CmsBlock } from "../lib/block-types";
import { validateCmsImageSrc, validateCmsUrl, externalLinkRel } from "../lib/validate-cms-url";
import { CmsSection } from "./CmsSection";
import { PublicImageSlot } from "@/features/public-theme-v2";

type CmsHeroProps = {
  block: Extract<CmsBlock, { type: "hero" }>;
};

export function CmsHero({ block }: CmsHeroProps) {
  const image = block.image ? validateCmsImageSrc(block.image.src) : null;

  return (
    <CmsSection>
      <div className="jp-v2-cms-hero">
        <div>
          {block.eyebrow ? <p className="jp-v2-cms-hero__eyebrow">{block.eyebrow}</p> : null}
          <h1 className="jp-v2-cms-hero__title">{block.heading}</h1>
          {block.body ? <p className="jp-v2-cms-hero__body">{block.body}</p> : null}
          {block.actions && block.actions.length > 0 ? (
            <div className="jp-v2-cms-hero__actions">
              {block.actions.map((action) => {
                const url = validateCmsUrl(action.href);
                if (!url.ok) return null;
                if (url.external) {
                  return (
                    <a
                      key={action.label}
                      href={url.href}
                      rel={externalLinkRel(true)}
                      className="jp-v2-btn jp-v2-btn--primary"
                    >
                      {action.label}
                    </a>
                  );
                }
                return (
                  <Link key={action.label} href={url.href} className="jp-v2-btn jp-v2-btn--primary">
                    {action.label}
                  </Link>
                );
              })}
            </div>
          ) : null}
        </div>
        {image?.ok ? (
          <PublicImageSlot src={image.href} alt={block.image!.alt} />
        ) : null}
      </div>
    </CmsSection>
  );
}
