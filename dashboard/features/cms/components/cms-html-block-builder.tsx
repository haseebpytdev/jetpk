"use client";

import { useMemo, useState } from "react";
import { mediaLibraryIndexPath } from "@/lib/api/portal-paths";
import { laravelRequest } from "@/lib/api/laravel-action-client";

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
  { key: "heading", label: "Heading", html: '<section data-jp-block="heading"><h2>Section heading</h2></section>' },
  { key: "paragraph", label: "Rich text", html: '<section data-jp-block="paragraph"><p>Write the page copy here.</p></section>' },
  { key: "image", label: "Image", html: '<section data-jp-block="image"><figure><img src="" alt="Describe this image" /><figcaption></figcaption></figure></section>' },
  { key: "image_text", label: "Image and text", html: '<section data-jp-block="image_text" data-layout="left"><figure><img src="" alt="Describe this image" /></figure><div><h3>Title</h3><p>Supporting copy.</p><p><a href="/">Learn more</a></p></div></section>' },
  { key: "cta", label: "Call to action", html: '<section data-jp-block="cta" data-variant="primary"><p><a href="/">Call to action</a></p></section>' },
  { key: "features", label: "Features", html: '<section data-jp-block="features"><ul><li><strong>Benefit one</strong><p>Short description</p></li><li><strong>Benefit two</strong><p>Short description</p></li></ul></section>' },
  { key: "stats", label: "Statistics", html: '<section data-jp-block="stats"><ul><li><strong>10k+</strong><span>travelers</span></li><li><strong>50+</strong><span>destinations</span></li></ul></section>' },
  { key: "offers", label: "Destination cards", html: '<section data-jp-block="offers"><article><img src="" alt="" /><h3>Lahore → Jeddah</h3><p>View fares</p><a href="/">Open</a></article></section>' },
  { key: "faq", label: "FAQ", html: '<section data-jp-block="faq"><article><h3>Question</h3><p>Answer</p></article></section>' },
  { key: "gallery", label: "Gallery", html: '<section data-jp-block="gallery"><figure><img src="" alt="Gallery image" /><figcaption></figcaption></figure></section>' },
  { key: "video", label: "Video", html: '<section data-jp-block="video"><a href="" data-jp-video>Watch video</a></section>' },
  { key: "testimonials", label: "Testimonials", html: '<section data-jp-block="testimonials"><blockquote><p>Traveler quote</p><cite>Customer name, title</cite></blockquote></section>' },
  { key: "steps", label: "Steps", html: '<section data-jp-block="steps"><ol><li><strong>Search</strong><p>Find flights</p></li><li><strong>Hold</strong><p>Reserve the fare</p></li></ol></section>' },
  { key: "callout", label: "Support callout", html: '<section data-jp-block="callout"><h3>Need help?</h3><p>Contact JetPakistan support.</p><p><a href="/support">Contact support</a></p></section>' },
  { key: "comparison", label: "Comparison cards", html: '<section data-jp-block="comparison"><article><h3>Option A</h3><p>Details</p></article><article><h3>Option B</h3><p>Details</p></article></section>' },
  { key: "tabs", label: "Tabs", html: '<section data-jp-block="tabs"><div data-jp-tab="Overview"><p>Overview content</p></div><div data-jp-tab="Details"><p>Details content</p></div></section>' },
  { key: "divider", label: "Divider", html: '<section data-jp-block="divider" aria-hidden="true"><hr /></section>' },
  { key: "spacer", label: "Safe spacer", html: '<section data-jp-block="spacer" data-size="md" aria-hidden="true"></section>' },
];

function escapeHtml(value: string): string {
  return value
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;");
}

function unescapeHtml(value: string): string {
  return value
    .replaceAll("&quot;", '"')
    .replaceAll("&gt;", ">")
    .replaceAll("&lt;", "<")
    .replaceAll("&amp;", "&");
}

function parseBlocks(content: string): Block[] {
  const matches = [...content.matchAll(/<section\s+([^>]*data-jp-block="([^"]+)"[^>]*)>([\s\S]*?)<\/section>/gi)];
  if (matches.length === 0 && content.trim() !== "") {
    return [{ id: "legacy-html", type: "html", html: content, hidden: false }];
  }
  return matches.map((match, index) => {
    const attrs = match[1] ?? "";
    const type = match[2] ?? "paragraph";
    return {
      id: `${type}-${index}`,
      type,
      html: match[0],
      hidden: /data-jp-hidden="true"/i.test(attrs),
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
  return unescapeHtml(match?.[1] ?? fallback);
}

function attr(html: string, name: string): string {
  const match = html.match(new RegExp(`${name}="([^"]*)"`, "i"));
  return match?.[1] ?? "";
}

function setAttr(html: string, name: string, value: string): string {
  if (new RegExp(`${name}="`, "i").test(html)) {
    return html.replace(new RegExp(`${name}="[^"]*"`, "i"), `${name}="${escapeHtml(value)}"`);
  }
  return html.replace("<section", `<section ${name}="${escapeHtml(value)}"`);
}

function Field({
  label,
  value,
  disabled,
  onChange,
  multiline,
}: {
  label: string;
  value: string;
  disabled?: boolean;
  onChange: (value: string) => void;
  multiline?: boolean;
}) {
  const cls = "mt-1 w-full rounded-lg border border-jp-border px-2 py-1 text-sm";
  return (
    <label className="block text-xs">
      {label}
      {multiline ? (
        <textarea className={cls} rows={3} disabled={disabled} value={value} onChange={(e) => onChange(e.target.value)} />
      ) : (
        <input className={cls} disabled={disabled} value={value} onChange={(e) => onChange(e.target.value)} />
      )}
    </label>
  );
}

type MediaLibraryItem = {
  url?: string | null;
  publicUrl?: string | null;
  name?: string;
  filename?: string;
  file_name?: string;
};

export function mediaOptionsFromLibraryResult(result: unknown): Array<{ url: string; name: string }> {
  const envelope = result as { data?: unknown; media?: unknown; assets?: unknown };
  const payload = (envelope.data ?? envelope) as { media?: unknown; assets?: unknown; data?: { media?: unknown } };
  const nested = payload.data?.media;
  const list = [payload.media, payload.assets, nested].find(Array.isArray) as MediaLibraryItem[] | undefined;
  return (list ?? [])
    .map((asset) => ({
      url: String(asset.publicUrl || asset.url || ""),
      name: String(asset.name || asset.file_name || asset.filename || asset.url || "Media"),
    }))
    .filter((item) => item.url !== "");
}

function MediaUrlField({
  label,
  value,
  disabled,
  onChange,
}: {
  label: string;
  value: string;
  disabled?: boolean;
  onChange: (value: string) => void;
}) {
  const [options, setOptions] = useState<Array<{ url: string; name: string }>>([]);
  return (
    <div className="grid gap-1">
      <Field label={label} value={value} disabled={disabled} onChange={onChange} />
      <button
        type="button"
        className="justify-self-start text-xs text-jp-accent"
        disabled={disabled}
        onClick={async () => {
          const result = await laravelRequest(mediaLibraryIndexPath(), { method: "GET" });
          setOptions(mediaOptionsFromLibraryResult(result));
        }}
      >
        Load media library
      </button>
      {options.length > 0 ? (
        <select className="rounded-lg border border-jp-border px-2 py-1 text-sm" value={value} onChange={(e) => onChange(e.target.value)}>
          <option value="">Select media</option>
          {options.map((item) => (
            <option key={item.url} value={item.url}>
              {item.name}
            </option>
          ))}
        </select>
      ) : null}
    </div>
  );
}

function listItems(html: string, tag: "li" | "article" | "blockquote"): string[] {
  return [...html.matchAll(new RegExp(`<${tag}[\\s\\S]*?<\\/${tag}>`, "gi"))].map((match) => match[0]);
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
      <Field
        label="Heading"
        value={firstMatch(block.html, /<h2>([\s\S]*?)<\/h2>/i)}
        disabled={disabled}
        onChange={(value) => onChange(block.html.replace(/<h2>[\s\S]*?<\/h2>/i, `<h2>${escapeHtml(value)}</h2>`))}
      />
    );
  }

  if (block.type === "paragraph") {
    return (
      <Field
        label="Formatted content"
        multiline
        value={firstMatch(block.html, /<p>([\s\S]*?)<\/p>/i)}
        disabled={disabled}
        onChange={(value) => onChange(block.html.replace(/<p>[\s\S]*?<\/p>/i, `<p>${escapeHtml(value).replaceAll("\n", "<br>")}</p>`))}
      />
    );
  }

  if (block.type === "image") {
    return (
      <div className="grid gap-2 sm:grid-cols-2">
        <MediaUrlField
          label="Image"
          value={attr(block.html, "src")}
          disabled={disabled}
          onChange={(value) => onChange(block.html.replace(/src="[^"]*"/i, `src="${escapeHtml(value)}"`))}
        />
        <Field label="Alt text" value={attr(block.html, "alt")} disabled={disabled} onChange={(value) => onChange(block.html.replace(/alt="[^"]*"/i, `alt="${escapeHtml(value)}"`))} />
        <Field
          label="Caption"
          value={firstMatch(block.html, /<figcaption>([\s\S]*?)<\/figcaption>/i)}
          disabled={disabled}
          onChange={(value) => onChange(block.html.replace(/<figcaption>[\s\S]*?<\/figcaption>/i, `<figcaption>${escapeHtml(value)}</figcaption>`))}
        />
      </div>
    );
  }

  if (block.type === "image_text") {
    return (
      <div className="grid gap-2">
        <MediaUrlField label="Image" value={attr(block.html, "src")} disabled={disabled} onChange={(value) => onChange(block.html.replace(/src="[^"]*"/i, `src="${escapeHtml(value)}"`))} />
        <Field label="Alt text" value={attr(block.html, "alt")} disabled={disabled} onChange={(value) => onChange(block.html.replace(/alt="[^"]*"/i, `alt="${escapeHtml(value)}"`))} />
        <Field label="Title" value={firstMatch(block.html, /<h3>([\s\S]*?)<\/h3>/i)} disabled={disabled} onChange={(value) => onChange(block.html.replace(/<h3>[\s\S]*?<\/h3>/i, `<h3>${escapeHtml(value)}</h3>`))} />
        <Field label="Body" multiline value={firstMatch(block.html, /<div>[\s\S]*?<p>([\s\S]*?)<\/p>/i)} disabled={disabled} onChange={(value) => onChange(block.html.replace(/(<div>[\s\S]*?<p>)([\s\S]*?)(<\/p>)/i, `$1${escapeHtml(value)}$3`))} />
        <label className="block text-xs">
          Layout
          <select className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1 text-sm" value={attr(block.html, "data-layout") || "left"} disabled={disabled} onChange={(e) => onChange(setAttr(block.html, "data-layout", e.target.value))}>
            <option value="left">Image left</option>
            <option value="right">Image right</option>
          </select>
        </label>
        <Field label="CTA label" value={firstMatch(block.html, /<a[^>]*>([\s\S]*?)<\/a>/i)} disabled={disabled} onChange={(value) => onChange(block.html.replace(/(<a[^>]*>)([\s\S]*?)(<\/a>)/i, `$1${escapeHtml(value)}$3`))} />
        <Field label="CTA destination" value={firstMatch(block.html, /<a[^>]*href="([^"]*)"/i)} disabled={disabled} onChange={(value) => onChange(block.html.replace(/href="[^"]*"/i, `href="${escapeHtml(value)}"`))} />
      </div>
    );
  }

  if (block.type === "cta") {
    return (
      <div className="grid gap-2 sm:grid-cols-3">
        <Field label="Label" value={firstMatch(block.html, /<a[^>]*>([\s\S]*?)<\/a>/i)} disabled={disabled} onChange={(value) => onChange(block.html.replace(/(<a[^>]*>)([\s\S]*?)(<\/a>)/i, `$1${escapeHtml(value)}$3`))} />
        <Field label="Destination" value={firstMatch(block.html, /<a[^>]*href="([^"]*)"/i)} disabled={disabled} onChange={(value) => onChange(block.html.replace(/href="[^"]*"/i, `href="${escapeHtml(value)}"`))} />
        <label className="block text-xs">
          Style
          <select className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1 text-sm" value={attr(block.html, "data-variant") || "primary"} disabled={disabled} onChange={(e) => onChange(setAttr(block.html, "data-variant", e.target.value))}>
            <option value="primary">Primary</option>
            <option value="secondary">Secondary</option>
          </select>
        </label>
      </div>
    );
  }

  if (block.type === "features" || block.type === "stats" || block.type === "steps") {
    const items = listItems(block.html, "li");
    const updateItem = (index: number, nextItem: string) => {
      const next = [...items];
      next[index] = nextItem;
      onChange(block.html.replace(/<(ul|ol)[\s\S]*<\/\1>/i, `<${block.type === "steps" ? "ol" : "ul"}>${next.join("")}</${block.type === "steps" ? "ol" : "ul"}>`));
    };
    return (
      <div className="space-y-2">
        {items.map((item, index) => (
          <div key={index} className="grid gap-2 rounded border border-jp-border p-2 sm:grid-cols-2">
            <Field
              label={block.type === "stats" ? "Value" : "Title"}
              value={firstMatch(item, /<strong>([\s\S]*?)<\/strong>/i) || item.replace(/<[^>]+>/g, "").trim()}
              disabled={disabled}
              onChange={(value) => updateItem(index, item.includes("<strong>") ? item.replace(/<strong>[\s\S]*?<\/strong>/i, `<strong>${escapeHtml(value)}</strong>`) : `<li><strong>${escapeHtml(value)}</strong></li>`)}
            />
            <Field
              label={block.type === "stats" ? "Label" : "Description"}
              value={firstMatch(item, /<(?:p|span)>([\s\S]*?)<\/(?:p|span)>/i)}
              disabled={disabled}
              onChange={(value) => {
                if (/<(?:p|span)>/.test(item)) {
                  updateItem(index, item.replace(/<(p|span)>[\s\S]*?<\/\1>/i, (full) => full.startsWith("<p") ? `<p>${escapeHtml(value)}</p>` : `<span>${escapeHtml(value)}</span>`));
                } else {
                  updateItem(index, item.replace("</li>", `<p>${escapeHtml(value)}</p></li>`));
                }
              }}
            />
            <button type="button" className="text-xs text-red-700" disabled={disabled} onClick={() => {
              const next = items.filter((_, i) => i !== index);
              onChange(block.html.replace(/<(ul|ol)[\s\S]*<\/\1>/i, `<${block.type === "steps" ? "ol" : "ul"}>${next.join("")}</${block.type === "steps" ? "ol" : "ul"}>`));
            }}>Remove row</button>
          </div>
        ))}
        <button
          type="button"
          className="rounded border border-jp-border px-2 py-1 text-xs"
          disabled={disabled}
          onClick={() => {
            const blank = block.type === "stats" ? "<li><strong>0</strong><span>label</span></li>" : "<li><strong>New item</strong><p>Description</p></li>";
            onChange(block.html.replace(/<\/(ul|ol)>/i, `${blank}</$1>`));
          }}
        >
          Add row
        </button>
      </div>
    );
  }

  if (block.type === "offers") {
    const cards = listItems(block.html, "article");
    return (
      <div className="space-y-2">
        {cards.map((card, index) => (
          <div key={index} className="grid gap-2 rounded border border-jp-border p-2">
            <MediaUrlField label="Image" value={attr(card, "src")} disabled={disabled} onChange={(value) => {
              const next = [...cards];
              next[index] = card.replace(/src="[^"]*"/i, `src="${escapeHtml(value)}"`);
              onChange(block.html.replace(/<article[\s\S]*<\/article>/gi, next.join("")));
            }} />
            <Field label="Destination / title" value={firstMatch(card, /<h3>([\s\S]*?)<\/h3>/i)} disabled={disabled} onChange={(value) => {
              const next = [...cards];
              next[index] = card.replace(/<h3>[\s\S]*?<\/h3>/i, `<h3>${escapeHtml(value)}</h3>`);
              onChange(block.html.replace(/<article[\s\S]*<\/article>/gi, next.join("")));
            }} />
            <Field label="Subtitle" value={firstMatch(card, /<p>([\s\S]*?)<\/p>/i)} disabled={disabled} onChange={(value) => {
              const next = [...cards];
              next[index] = card.replace(/<p>[\s\S]*?<\/p>/i, `<p>${escapeHtml(value)}</p>`);
              onChange(block.html.replace(/<article[\s\S]*<\/article>/gi, next.join("")));
            }} />
            <Field label="Link" value={firstMatch(card, /href="([^"]*)"/i)} disabled={disabled} onChange={(value) => {
              const next = [...cards];
              next[index] = card.replace(/href="[^"]*"/i, `href="${escapeHtml(value)}"`);
              onChange(block.html.replace(/<article[\s\S]*<\/article>/gi, next.join("")));
            }} />
          </div>
        ))}
        <button type="button" className="rounded border border-jp-border px-2 py-1 text-xs" disabled={disabled} onClick={() => onChange(block.html.replace("</section>", '<article><img src="" alt="" /><h3>New destination</h3><p>Subtitle</p><a href="/">Open</a></article></section>'))}>Add card</button>
      </div>
    );
  }

  if (block.type === "faq") {
    const items = listItems(block.html, "article");
    return (
      <div className="space-y-2">
        {items.map((item, index) => (
          <div key={index} className="grid gap-2 rounded border border-jp-border p-2">
            <Field label="Question" value={firstMatch(item, /<h3>([\s\S]*?)<\/h3>/i)} disabled={disabled} onChange={(value) => {
              const next = [...items];
              next[index] = item.replace(/<h3>[\s\S]*?<\/h3>/i, `<h3>${escapeHtml(value)}</h3>`);
              onChange(block.html.replace(/<article[\s\S]*<\/article>/gi, next.join("")));
            }} />
            <Field label="Answer" multiline value={firstMatch(item, /<p>([\s\S]*?)<\/p>/i)} disabled={disabled} onChange={(value) => {
              const next = [...items];
              next[index] = item.replace(/<p>[\s\S]*?<\/p>/i, `<p>${escapeHtml(value)}</p>`);
              onChange(block.html.replace(/<article[\s\S]*<\/article>/gi, next.join("")));
            }} />
          </div>
        ))}
        <button type="button" className="rounded border border-jp-border px-2 py-1 text-xs" disabled={disabled} onClick={() => onChange(block.html.replace("</section>", "<article><h3>Question</h3><p>Answer</p></article></section>"))}>Add question</button>
      </div>
    );
  }

  if (block.type === "gallery") {
    const figures = [...block.html.matchAll(/<figure[\s\S]*?<\/figure>/gi)].map((match) => match[0]);
    return (
      <div className="space-y-2">
        {figures.map((figure, index) => (
          <div key={index} className="grid gap-2 rounded border border-jp-border p-2 sm:grid-cols-2">
            <MediaUrlField label="Media" value={attr(figure, "src")} disabled={disabled} onChange={(value) => {
              const next = [...figures];
              next[index] = figure.replace(/src="[^"]*"/i, `src="${escapeHtml(value)}"`);
              onChange(block.html.replace(/<figure[\s\S]*<\/figure>/gi, next.join("")));
            }} />
            <Field label="Alt" value={attr(figure, "alt")} disabled={disabled} onChange={(value) => {
              const next = [...figures];
              next[index] = figure.replace(/alt="[^"]*"/i, `alt="${escapeHtml(value)}"`);
              onChange(block.html.replace(/<figure[\s\S]*<\/figure>/gi, next.join("")));
            }} />
            <Field label="Caption" value={firstMatch(figure, /<figcaption>([\s\S]*?)<\/figcaption>/i)} disabled={disabled} onChange={(value) => {
              const next = [...figures];
              next[index] = /<figcaption>/.test(figure)
                ? figure.replace(/<figcaption>[\s\S]*?<\/figcaption>/i, `<figcaption>${escapeHtml(value)}</figcaption>`)
                : figure.replace("</figure>", `<figcaption>${escapeHtml(value)}</figcaption></figure>`);
              onChange(block.html.replace(/<figure[\s\S]*<\/figure>/gi, next.join("")));
            }} />
            <div className="flex gap-1">
              <button type="button" className="rounded border px-2 py-1 text-xs" disabled={disabled || index === 0} onClick={() => {
                const next = [...figures];
                [next[index - 1], next[index]] = [next[index], next[index - 1]];
                onChange(block.html.replace(/<figure[\s\S]*<\/figure>/gi, next.join("")));
              }}>Up</button>
              <button type="button" className="rounded border px-2 py-1 text-xs" disabled={disabled} onClick={() => {
                const next = figures.filter((_, i) => i !== index);
                onChange(block.html.replace(/<figure[\s\S]*<\/figure>/gi, next.join("")));
              }}>Remove</button>
            </div>
          </div>
        ))}
        <button type="button" className="rounded border border-jp-border px-2 py-1 text-xs" disabled={disabled} onClick={() => onChange(block.html.replace("</section>", '<figure><img src="" alt="" /><figcaption></figcaption></figure></section>'))}>Add media</button>
      </div>
    );
  }

  if (block.type === "video") {
    return (
      <div className="grid gap-2 sm:grid-cols-2">
        <Field
          label="Approved video URL"
          value={firstMatch(block.html, /href="([^"]*)"/i) || firstMatch(block.html, /src="([^"]*)"/i)}
          disabled={disabled}
          onChange={(value) => onChange(block.html.replace(/href="[^"]*"/i, `href="${escapeHtml(value)}"`).replace(/src="[^"]*"/i, `src="${escapeHtml(value)}"`))}
        />
        <Field label="Label" value={firstMatch(block.html, />([\s\S]*?)<\/a>/i)} disabled={disabled} onChange={(value) => onChange(block.html.replace(/(>)([\s\S]*?)(<\/a>)/i, `$1${escapeHtml(value)}$3`))} />
      </div>
    );
  }

  if (block.type === "testimonials") {
    const quotes = listItems(block.html, "blockquote");
    return (
      <div className="space-y-2">
        {quotes.map((quote, index) => (
          <div key={index} className="grid gap-2 rounded border border-jp-border p-2">
            <Field label="Quote" multiline value={firstMatch(quote, /<p>([\s\S]*?)<\/p>/i)} disabled={disabled} onChange={(value) => {
              const next = [...quotes];
              next[index] = quote.replace(/<p>[\s\S]*?<\/p>/i, `<p>${escapeHtml(value)}</p>`);
              onChange(block.html.replace(/<blockquote[\s\S]*<\/blockquote>/gi, next.join("")));
            }} />
            <Field label="Name / title" value={firstMatch(quote, /<cite>([\s\S]*?)<\/cite>/i)} disabled={disabled} onChange={(value) => {
              const next = [...quotes];
              next[index] = quote.replace(/<cite>[\s\S]*?<\/cite>/i, `<cite>${escapeHtml(value)}</cite>`);
              onChange(block.html.replace(/<blockquote[\s\S]*<\/blockquote>/gi, next.join("")));
            }} />
          </div>
        ))}
        <button type="button" className="rounded border border-jp-border px-2 py-1 text-xs" disabled={disabled} onClick={() => onChange(block.html.replace("</section>", "<blockquote><p>Quote</p><cite>Name, title</cite></blockquote></section>"))}>Add testimonial</button>
      </div>
    );
  }

  if (block.type === "callout") {
    return (
      <div className="grid gap-2">
        <Field label="Heading" value={firstMatch(block.html, /<h3>([\s\S]*?)<\/h3>/i)} disabled={disabled} onChange={(value) => onChange(block.html.replace(/<h3>[\s\S]*?<\/h3>/i, `<h3>${escapeHtml(value)}</h3>`))} />
        <Field label="Body" multiline value={firstMatch(block.html, /<p>([\s\S]*?)<\/p>/i)} disabled={disabled} onChange={(value) => onChange(block.html.replace(/<p>[\s\S]*?<\/p>/i, `<p>${escapeHtml(value)}</p>`))} />
        <Field label="CTA label" value={firstMatch(block.html, /<a[^>]*>([\s\S]*?)<\/a>/i)} disabled={disabled} onChange={(value) => onChange(block.html.replace(/(<a[^>]*>)([\s\S]*?)(<\/a>)/i, `$1${escapeHtml(value)}$3`))} />
        <Field label="CTA / contact destination" value={firstMatch(block.html, /href="([^"]*)"/i)} disabled={disabled} onChange={(value) => onChange(block.html.replace(/href="[^"]*"/i, `href="${escapeHtml(value)}"`))} />
      </div>
    );
  }

  if (block.type === "html") {
    return <p className="text-xs text-jp-muted">Legacy HTML section. Use Advanced HTML if you need to edit the raw markup.</p>;
  }

  return <p className="text-xs text-jp-muted">This section type is not in the approved catalogue.</p>;
}

export function CmsHtmlBlockBuilder({ content, onChange, disabled }: Props) {
  const blocks = useMemo(() => parseBlocks(content), [content]);

  function update(next: Block[]) {
    onChange(serialize(next));
  }

  function insert(type: string, html: string, at: number) {
    const next = [...blocks];
    next.splice(at, 0, { id: `${type}-${Date.now()}`, type, html, hidden: false });
    update(next);
  }

  return (
    <div className="rounded-xl border border-jp-border bg-white p-3" data-testid="cms-html-block-builder">
      <p className="text-xs font-semibold text-gray-900">Page builder</p>
      <p className="mt-1 text-xs text-jp-muted">
        Add approved sections with business fields. Advanced HTML remains an expert fallback.
      </p>
      <div className="mt-2 flex flex-wrap gap-2">
        {CATALOGUE.map((block) => (
          <button
            key={block.key}
            type="button"
            disabled={disabled}
            className="min-h-11 rounded-xl border border-jp-border bg-white px-3 text-sm disabled:opacity-50"
            onClick={() => insert(block.key, block.html, blocks.length)}
          >
            Add {block.label.toLowerCase()}
          </button>
        ))}
      </div>
      <ul className="mt-3 space-y-2">
        {blocks.map((block, index) => (
          <li key={block.id} className="space-y-2 rounded-lg border border-jp-border px-3 py-2 text-sm">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <span className={block.hidden ? "text-jp-muted line-through" : "font-medium"}>{block.type}</span>
              <div className="flex flex-wrap gap-1">
                <button type="button" className="rounded border border-jp-border px-2 py-1 text-xs" disabled={disabled} onClick={() => insert("paragraph", CATALOGUE[1].html, index)}>Insert above</button>
                <button type="button" className="rounded border border-jp-border px-2 py-1 text-xs" disabled={disabled} onClick={() => insert("paragraph", CATALOGUE[1].html, index + 1)}>Insert below</button>
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
                  next.splice(index + 1, 0, { ...block, id: `${block.type}-copy-${Date.now()}` });
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
