import type { CmsBlock } from "../lib/block-types";
import { validateCmsImageSrc } from "../lib/validate-cms-url";
import { CmsSection } from "./CmsSection";
import { PublicImageSlot } from "@/features/public-theme-v2";

type CmsImageProps = {
  block: Extract<CmsBlock, { type: "image" }>;
};

export function CmsImage({ block }: CmsImageProps) {
  const src = validateCmsImageSrc(block.image.src);
  if (!src.ok) return null;

  return (
    <CmsSection width={block.width === "full" ? "wide" : "default"}>
      <figure>
        <PublicImageSlot src={src.href} alt={block.image.alt} />
        {block.caption ? (
          <figcaption style={{ marginTop: "var(--jp-v2-space-sm)", fontSize: "var(--jp-v2-text-sm)", color: "var(--jp-v2-text-muted)" }}>
            {block.caption}
          </figcaption>
        ) : null}
      </figure>
    </CmsSection>
  );
}
