import Link from "next/link";
import type { CmsBlock } from "../lib/block-types";
import { validateCmsUrl, externalLinkRel } from "../lib/validate-cms-url";
import { PublicCard } from "@/features/public-theme-v2";
import { CmsSection } from "./CmsSection";

type CmsCardGridProps = {
  block: Extract<CmsBlock, { type: "cardGrid" }>;
};

export function CmsCardGrid({ block }: CmsCardGridProps) {
  const cols = block.columns ?? 3;

  return (
    <CmsSection>
      {block.heading ? <h2 style={{ marginBottom: "var(--jp-v2-space-md)" }}>{block.heading}</h2> : null}
      {block.body ? <p style={{ color: "var(--jp-v2-text-muted)", marginBottom: "var(--jp-v2-space-lg)" }}>{block.body}</p> : null}
      <div className={`jp-v2-cms-grid jp-v2-cms-grid--${cols}`}>
        {block.items.map((item) => {
          const url = item.href ? validateCmsUrl(item.href) : null;
          const content = (
            <PublicCard interactive={!!url?.ok}>
              {item.icon ? <p style={{ margin: "0 0 var(--jp-v2-space-sm)" }}>{item.icon}</p> : null}
              <h3 style={{ margin: "0 0 var(--jp-v2-space-sm)", fontSize: "var(--jp-v2-heading-sm)" }}>{item.heading}</h3>
              {item.body ? <p style={{ margin: 0, color: "var(--jp-v2-text-muted)", fontSize: "var(--jp-v2-text-sm)" }}>{item.body}</p> : null}
            </PublicCard>
          );

          if (url?.ok && !url.external) {
            return (
              <Link key={item.heading} href={url.href} style={{ textDecoration: "none", color: "inherit" }}>
                {content}
              </Link>
            );
          }
          if (url?.ok && url.external) {
            return (
              <a key={item.heading} href={url.href} rel={externalLinkRel(true)} style={{ textDecoration: "none", color: "inherit" }}>
                {content}
              </a>
            );
          }
          return <div key={item.heading}>{content}</div>;
        })}
      </div>
    </CmsSection>
  );
}
