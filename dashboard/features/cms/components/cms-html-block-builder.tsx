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
  { key: "paragraph", label: "Rich text", html: "<section data-jp-block=\"paragraph\"><p>Write the page copy here.</p></section>" },
  { key: "image", label: "Image", html: "<section data-jp-block=\"image\"><figure><img src=\"\" alt=\"Describe this image\" /><figcaption>Caption</figcaption></figure></section>" },
  { key: "image_text", label: "Image and text", html: "<section data-jp-block=\"image_text\"><figure><img src=\"\" alt=\"Describe this image\" /></figure><div><h3>Title</h3><p>Supporting copy.</p></div></section>" },
  { key: "cta", label: "Call to action", html: "<section data-jp-block=\"cta\"><p><a href=\"/\">Call to action</a></p></section>" },
  { key: "features", label: "Features", html: "<section data-jp-block=\"features\"><ul><li>Benefit one</li><li>Benefit two</li><li>Benefit three</li></ul></section>" },
  { key: "stats", label: "Statistics", html: "<section data-jp-block=\"stats\"><p><strong>10k+</strong> travelers</p></section>" },
  { key: "offers", label: "Destination cards", html: "<section data-jp-block=\"offers\"><article><h3>Lahore → Jeddah</h3><p>View fares</p></article></section>" },
  { key: "faq", label: "FAQ", html: "<section data-jp-block=\"faq\"><h3>Question</h3><p>Answer</p></section>" },
  { key: "gallery", label: "Gallery", html: "<section data-jp-block=\"gallery\"><figure><img src=\"\" alt=\"Gallery image\" /></figure></section>" },
  { key: "video", label: "Video", html: "<section data-jp-block=\"video\"><p>Paste an approved video embed here.</p></section>" },
  { key: "testimonials", label: "Testimonials", html: "<section data-jp-block=\"testimonials\"><blockquote><p>Traveler quote</p><cite>Customer name</cite></blockquote></section>" },
  { key: "steps", label: "Steps", html: "<section data-jp-block=\"steps\"><ol><li>Search</li><li>Hold</li><li>Pay</li></ol></section>" },
  { key: "callout", label: "Support callout", html: "<section data-jp-block=\"callout\"><p>Need help? Contact JetPakistan support.</p></section>" },
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

function firstMatch(html: string, pattern: RegExp, fallback = ""): string {
  const match = html.match(pattern);
  return match?.[1] ?? fallback;
}

function replaceFirst(html: string, pattern: RegExp, next: string): string {
  return html.replace(pattern, next);
}

function BlockFields({
  block,
  disabled,
  onChange,
}: {
  block: Block;
  disabled?: boolean;
  onChange: (html: string) => void;
}) {
  if (block.type === "heading") {
    return (
      <input
        className="w-full rounded-lg border border-jp-border px-2 py-1 text-sm"
        disabled={disabled}
        value={firstMatch(block.html, /<h2>([\s\S]*?)<\/h2>/i)}
        onChange={(e) => onChange(replaceFirst(block.html, /<h2>([\s\S]*?)<\/h2>/i, `<h2>${e.target.value}</h2>`))}
        aria-label="Heading text"
      />
    );
  }

  if (block.type === "paragraph") {
    return (
      <textarea
        className="w-full rounded-lg border border-jp-border px-2 py-1 text-sm"
        rows={3}
        disabled={disabled}
        value={firstMatch(block.html, /<p>([\s\S]*?)<\/p>/i)}
        onChange={(e) => onChange(replaceFirst(block.html, /<p>([\s\S]*?)<\/p>/i, `<p>${e.target.value}</p>`))}
        aria-label="Rich text"
      />
    );
  }

  if (block.type === "image" || block.type === "gallery" || block.type === "image_text") {
    const src = firstMatch(block.html, /<img[^>]*src="([^"]*)"/i);
    const alt = firstMatch(block.html, /<img[^>]*alt="([^"]*)"/i);
    return (
      <div className="grid gap-2 sm:grid-cols-2">
        <input
          className="rounded-lg border border-jp-border px-2 py-1 text-sm"
          disabled={disabled}
          placeholder="Image URL (paste from Media library)"
          value={src}
          onChange={(e) => onChange(block.html.replace(/src="[^"]*"/i, `src="${e.target.value}"`))}
          aria-label="Image URL"
        />
        <input
          className="rounded-lg border border-jp-border px-2 py-1 text-sm"
          disabled={disabled}
          placeholder="Alt text"
          value={alt}
          onChange={(e) => onChange(block.html.replace(/alt="[^"]*"/i, `alt="${e.target.value}"`))}
          aria-label="Image alt text"
        />
      </div>
    );
  }

  if (block.type === "cta") {
    return (
      <div className="grid gap-2 sm:grid-cols-2">
        <input
          className="rounded-lg border border-jp-border px-2 py-1 text-sm"
          disabled={disabled}
          value={firstMatch(block.html, /<a[^>]*href="([^"]*)"/i)}
          onChange={(e) => onChange(block.html.replace(/href="[^"]*"/i, `href="${e.target.value}"`))}
          aria-label="Call to action URL"
        />
        <input
          className="rounded-lg border border-jp-border px-2 py-1 text-sm"
          disabled={disabled}
          value={firstMatch(block.html, /<a[^>]*>([\s\S]*?)<\/a>/i)}
          onChange={(e) => onChange(block.html.replace(/(<a[^>]*>)([\s\S]*?)(<\/a>)/i, (_full, open, _inner, close) => `${open}${e.target.value}${close}`))}
          aria-label="Call to action label"
        />
      </div>
    );
  }

  return <p className="text-xs text-jp-muted">Edit this section in the fields above, or use Advanced HTML if needed.</p>;
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
          <li key={block.id} className="space-y-2 rounded-lg border border-jp-border px-3 py-2 text-sm">
            <div className="flex flex-wrap items-center justify-between gap-2">
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
            </div>
            <BlockFields
              block={block}
              disabled={disabled}
              onChange={(html) => {
                const next = [...blocks];
                next[index] = { ...block, html };
                update(next);
              }}
            />
          </li>
        ))}
      </ul>
    </div>
  );
}
