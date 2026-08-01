import type { CmsBlock } from "../lib/block-types";
import { validateCmsImageSrc } from "../lib/validate-cms-url";
import { PublicImageSlot } from "@/features/public-theme-v2";
import { CmsSection } from "./CmsSection";

type CmsGalleryProps = {
  block: Extract<CmsBlock, { type: "gallery" }>;
};

export function CmsGallery({ block }: CmsGalleryProps) {
  const cols = block.columns ?? 3;
  const items = block.items
    .map((item) => {
      const src = validateCmsImageSrc(item.src);
      return src.ok ? { ...item, src: src.href } : null;
    })
    .filter((item): item is { src: string; alt: string } => item !== null);

  if (items.length === 0) return null;

  return (
    <CmsSection>
      {block.heading ? <h2 style={{ marginBottom: "var(--jp-v2-space-lg)" }}>{block.heading}</h2> : null}
      <div className={`jp-v2-cms-grid jp-v2-cms-grid--${cols}`}>
        {items.map((item) => (
          <PublicImageSlot key={item.src} src={item.src} alt={item.alt} />
        ))}
      </div>
    </CmsSection>
  );
}
