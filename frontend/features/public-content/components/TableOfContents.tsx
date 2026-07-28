import Link from "next/link";
import type { LegalSection } from "../types";

type TableOfContentsProps = {
  sections: LegalSection[];
};

export function TableOfContents({ sections }: TableOfContentsProps) {
  if (!sections.length) return null;

  return (
    <nav aria-label="Table of contents" className="rounded-jp-lg border border-jp-border bg-jp-surface p-jp-lg">
      <h2 className="text-jp-sm font-semibold uppercase tracking-wide text-jp-text">On this page</h2>
      <ol className="mt-3 space-y-2 text-jp-sm">
        {sections.map((section) => (
          <li key={section.id}>
            <Link
              href={`#${section.id}`}
              className="text-jp-muted transition-colors hover:text-jp-primary focus-visible:outline-none focus-visible:shadow-jp-focus"
            >
              {section.heading}
            </Link>
          </li>
        ))}
      </ol>
    </nav>
  );
}
