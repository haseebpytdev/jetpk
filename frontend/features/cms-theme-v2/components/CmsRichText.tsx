import type { CmsBlock } from "../lib/block-types";
import { sanitizeCmsHtml } from "../lib/sanitize-cms-html";
import { CmsSection } from "./CmsSection";
import "../styles/cms-content.css";

type CmsRichTextProps = {
  block: Extract<CmsBlock, { type: "richText" }>;
};

export function CmsRichText({ block }: CmsRichTextProps) {
  const safe = sanitizeCmsHtml(block.html);
  if (!safe) return null;

  return (
    <CmsSection width="narrow">
      <article
        className="jp-cms-content"
        dangerouslySetInnerHTML={{ __html: safe }}
      />
    </CmsSection>
  );
}
