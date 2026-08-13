"use client";

import { useMemo } from "react";

type Block = {
  id: string;
  type: string;
  html: string;
  hidden: boolean;
};

type Props = {
  content: string;
  onChange: (html: string) => void;
  disabled?: boolean;
};

const CATALOGUE: { key: string; label: string; html: string }[] = [
  { key: "heading", label: "Heading", html: "<section data-jp-block=\"heading\"><h2>Section heading</h2></section>" },
  { key: "paragraph", label: "Paragraph", html: "<section data-jp-block=\"paragraph\"><p>Write the page copy here.</p></section>" },
  { key: "image", label: "Image", html: "<section data-jp-block=\"image\"><figure><img src=\"\" alt=\"Describe this image\" /><figcaption>Caption</figcaption></figure></section>" },
  { key: "cta", label: "Call to action", html: "<section data-jp-block=\"cta\"><p><a href=\"/\">Call to action</a></p></section>" },
];

function parseBlocks(content: string): Block[] {
  const matches = [...content.matchAll(/<section\s+([^>]*data-jp-block="([^"]+)"[^>]*)>([\s\S]*?)<\/section>/gi)];
  if (matches.length === 0 && content.trim() !== "") {
    return [{ id: "legacy-html", type: "html", html: content, hidden: false }];
  }
  return matches.map((match, index) => {
    const attrs = match[1] ?? "";
    const type = match[2] ?? "paragraph";
    const hidden = /data-jp-hidden="true"/i.test(attrs);
    return {
      id: `${type}-${index}`,
      type,
      html: match[0],
      hidden,
    };
  });
}

function serialize(blocks: Block[]): string {
  return blocks
    .map((block) => {
      if (block.type === "html") return block.hidden ? "" : block.html;
      const withoutHidden = block.html.replace(/\sdata-jp-hidden="true"/i, "");
      return block.hidden ? withoutHidden.replace("<section", '<section data-jp-hidden="true"') : withoutHidden;
    })
    .filter(Boolean)
    .join("\n");
}

export function CmsHtmlBlockBuilder({ content, onChange, disabled }: Props) {
  const blocks = useMemo(() => parseBlocks(content), [content]);

  function update(next: Block[]) {
    onChange(serialize(next));
  }

  return (
    <div className="rounded-xl border border-jp-border bg-white p-3" data-testid="cms-html-block-builder">
      <p className="text-xs font-semibold text-gray-900">Page builder</p>
      <p className="mt-1 text-xs text-jp-muted">
        Add, reorder, hide, duplicate, or remove approved sections. Public pages render these sections.
      </p>
      <div className="mt-2 flex flex-wrap gap-2">
        {CATALOGUE.map((block) => (
          <button
            key={block.key}
            type="button"
            disabled={disabled}
            className="min-h-11 rounded-xl border border-jp-border bg-white px-3 text-sm disabled:opacity-50"
            onClick={() => update([...blocks, { id: `${block.key}-${blocks.length}`, type: block.key, html: block.html, hidden: false }])}
          >
            Add {block.label.toLowerCase()}
          </button>
        ))}
      </div>
      <ul className="mt-3 space-y-2">
        {blocks.map((block, index) => (
          <li key={block.id} className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-jp-border px-3 py-2 text-sm">
            <span className={block.hidden ? "text-jp-muted line-through" : "font-medium"}>
              {block.type}
            </span>
            <div className="flex flex-wrap gap-1">
              <button type="button" className="rounded border border-jp-border px-2 py-1 text-xs" disabled={disabled || index === 0} onClick={() => {
                const next = [...blocks];
                [next[index - 1], next[index]] = [next[index], next[index - 1]];
                update(next);
              }}>Up</button>
              <button type="button" className="rounded border border-jp-border px-2 py-1 text-xs" disabled={disabled || index === blocks.length - 1} onClick={() => {
                const next = [...blocks];
                [next[index + 1], next[index]] = [next[index], next[index + 1]];
                update(next);
              }}>Down</button>
              <button type="button" className="rounded border border-jp-border px-2 py-1 text-xs" disabled={disabled} onClick={() => {
                const next = [...blocks];
                next.splice(index + 1, 0, { ...block, id: `${block.type}-copy-${blocks.length}` });
                update(next);
              }}>Duplicate</button>
              <button type="button" className="rounded border border-jp-border px-2 py-1 text-xs" disabled={disabled} onClick={() => {
                const next = [...blocks];
                next[index] = { ...block, hidden: !block.hidden };
                update(next);
              }}>{block.hidden ? "Show" : "Hide"}</button>
              <button type="button" className="rounded border border-red-200 px-2 py-1 text-xs text-red-700" disabled={disabled} onClick={() => update(blocks.filter((_, i) => i !== index))}>Remove</button>
            </div>
          </li>
        ))}
      </ul>
    </div>
  );
}
