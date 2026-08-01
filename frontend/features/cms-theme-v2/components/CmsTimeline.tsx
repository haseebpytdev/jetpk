import type { CmsBlock } from "../lib/block-types";
import { CmsSection } from "./CmsSection";

type CmsTimelineProps = {
  block: Extract<CmsBlock, { type: "timeline" }>;
};

export function CmsTimeline({ block }: CmsTimelineProps) {
  return (
    <CmsSection>
      {block.heading ? <h2 style={{ marginBottom: "var(--jp-v2-space-lg)" }}>{block.heading}</h2> : null}
      <ol className="jp-v2-cms-timeline">
        {block.items.map((item) => (
          <li key={`${item.marker}-${item.heading}`} className="jp-v2-cms-timeline__item">
            <p style={{ margin: "0 0 var(--jp-v2-space-xs)", fontSize: "var(--jp-v2-text-xs)", color: "var(--jp-v2-brand)", fontWeight: 600 }}>
              {item.marker}
            </p>
            <h3 style={{ margin: "0 0 var(--jp-v2-space-xs)", fontSize: "var(--jp-v2-heading-sm)" }}>{item.heading}</h3>
            {item.body ? <p style={{ margin: 0, color: "var(--jp-v2-text-muted)" }}>{item.body}</p> : null}
          </li>
        ))}
      </ol>
    </CmsSection>
  );
}
