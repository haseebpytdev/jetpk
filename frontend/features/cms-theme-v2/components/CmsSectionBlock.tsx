import type { CmsBlock } from "../lib/block-types";
import { CmsSection } from "./CmsSection";
import { CmsBlockRenderer } from "./CmsBlockRenderer";

type CmsSectionBlockProps = {
  block: Extract<CmsBlock, { type: "section" }>;
  showDevMarkers?: boolean;
};

export function CmsSectionBlock({ block, showDevMarkers }: CmsSectionBlockProps) {
  return (
    <CmsSection>
      {block.heading ? <h2 style={{ marginBottom: "var(--jp-v2-space-md)" }}>{block.heading}</h2> : null}
      {block.body ? <p style={{ color: "var(--jp-v2-text-muted)", marginBottom: "var(--jp-v2-space-lg)" }}>{block.body}</p> : null}
      {block.blocks?.map((child, index) => (
        <CmsBlockRenderer key={`${child.type}-${index}`} block={child} showDevMarkers={showDevMarkers} />
      ))}
    </CmsSection>
  );
}
