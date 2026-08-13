"use client";

type Props = {
  onInsert: (html: string) => void;
  disabled?: boolean;
};

const BLOCKS: { key: string; label: string; html: string }[] = [
  {
    key: "heading",
    label: "Heading",
    html: "<section data-jp-block=\"heading\"><h2>Section heading</h2></section>\n",
  },
  {
    key: "paragraph",
    label: "Paragraph",
    html: "<section data-jp-block=\"paragraph\"><p>Write the page copy here.</p></section>\n",
  },
  {
    key: "image",
    label: "Image",
    html: "<section data-jp-block=\"image\"><figure><img src=\"\" alt=\"Describe this image\" /><figcaption>Caption</figcaption></figure></section>\n",
  },
  {
    key: "cta",
    label: "Call to action",
    html: "<section data-jp-block=\"cta\"><p><a href=\"/\">Call to action</a></p></section>\n",
  },
];

export function CmsHtmlBlockBuilder({ onInsert, disabled }: Props) {
  return (
    <div className="rounded-xl border border-jp-border bg-white p-3" data-testid="cms-html-block-builder">
      <p className="text-xs font-semibold text-gray-900">Add content block</p>
      <p className="mt-1 text-xs text-jp-muted">
        Inserts approved HTML sections into this page. Reorder by moving the sections in the content field, then save.
      </p>
      <div className="mt-2 flex flex-wrap gap-2">
        {BLOCKS.map((block) => (
          <button
            key={block.key}
            type="button"
            disabled={disabled}
            className="min-h-11 rounded-xl border border-jp-border bg-white px-3 text-sm disabled:opacity-50"
            onClick={() => onInsert(block.html)}
          >
            {block.label}
          </button>
        ))}
      </div>
    </div>
  );
}
