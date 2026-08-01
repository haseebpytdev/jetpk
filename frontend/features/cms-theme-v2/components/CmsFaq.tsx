import type { CmsBlock } from "../lib/block-types";
import { CmsSection } from "./CmsSection";

type CmsFaqProps = {
  block: Extract<CmsBlock, { type: "faq" }>;
};

export function CmsFaq({ block }: CmsFaqProps) {
  return (
    <CmsSection width="narrow">
      {block.heading ? <h2 style={{ marginBottom: "var(--jp-v2-space-lg)" }}>{block.heading}</h2> : null}
      <div className="jp-v2-cms-faq">
        {block.items.map((item) => (
          <details key={item.question}>
            <summary>{item.question}</summary>
            <div className="jp-v2-cms-faq__answer">{item.answer}</div>
          </details>
        ))}
      </div>
    </CmsSection>
  );
}
