import type { CmsBlock } from "../lib/block-types";
import { CmsHero } from "./CmsHero";
import { CmsRichText } from "./CmsRichText";
import { CmsImage } from "./CmsImage";
import { CmsCardGrid } from "./CmsCardGrid";
import { CmsStats } from "./CmsStats";
import { CmsTimeline } from "./CmsTimeline";
import { CmsFaq } from "./CmsFaq";
import { CmsCallout } from "./CmsCallout";
import { CmsGallery } from "./CmsGallery";
import { CmsSectionBlock } from "./CmsSectionBlock";

type CmsBlockRendererProps = {
  block: CmsBlock;
  showDevMarkers?: boolean;
};

export function CmsBlockRenderer({ block, showDevMarkers = false }: CmsBlockRendererProps) {
  switch (block.type) {
    case "hero":
      return <CmsHero block={block} />;
    case "richText":
      return <CmsRichText block={block} />;
    case "image":
      return <CmsImage block={block} />;
    case "cardGrid":
      return <CmsCardGrid block={block} />;
    case "stats":
      return <CmsStats block={block} />;
    case "timeline":
      return <CmsTimeline block={block} />;
    case "faq":
      return <CmsFaq block={block} />;
    case "callout":
      return <CmsCallout block={block} />;
    case "gallery":
      return <CmsGallery block={block} />;
    case "section":
      return <CmsSectionBlock block={block} showDevMarkers={showDevMarkers} />;
    default: {
      if (showDevMarkers) {
        return (
          <div className="jp-v2-cms-dev-marker" role="status">
            Unknown CMS block type skipped in production.
          </div>
        );
      }
      return null;
    }
  }
}
