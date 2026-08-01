import type { CmsBlock } from "../lib/block-types";
import { CmsSection } from "./CmsSection";

type CmsStatsProps = {
  block: Extract<CmsBlock, { type: "stats" }>;
};

export function CmsStats({ block }: CmsStatsProps) {
  return (
    <CmsSection>
      {block.heading ? <h2 style={{ marginBottom: "var(--jp-v2-space-lg)" }}>{block.heading}</h2> : null}
      <div className="jp-v2-cms-stats">
        {block.items.map((item) => (
          <div key={item.label}>
            {item.icon ? <p style={{ margin: "0 0 var(--jp-v2-space-xs)" }}>{item.icon}</p> : null}
            <p className="jp-v2-cms-stat__value">{item.value}</p>
            <p className="jp-v2-cms-stat__label">{item.label}</p>
          </div>
        ))}
      </div>
    </CmsSection>
  );
}
