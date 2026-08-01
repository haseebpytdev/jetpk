import type { CmsPagePayload } from "../lib/block-types";
import { PublicEmptyState } from "@/features/public-theme-v2";
import { CmsBlockRenderer } from "./CmsBlockRenderer";
import "../styles/cms-content.css";

type CmsPageRendererProps = {
  page: CmsPagePayload;
  showDevMarkers?: boolean;
};

export function CmsPageRenderer({ page, showDevMarkers = false }: CmsPageRendererProps) {
  if (!page.blocks.length) {
    return (
      <PublicEmptyState
        title="No content yet"
        description="This page has no CMS blocks to display."
      />
    );
  }

  return (
    <div data-cms-template={page.template}>
      {page.blocks.map((block, index) => (
        <CmsBlockRenderer key={`${block.type}-${index}`} block={block} showDevMarkers={showDevMarkers} />
      ))}
    </div>
  );
}
