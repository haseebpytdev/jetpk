import { cn } from "@/lib/cn";
import type { ContentCard } from "../types";
import { ContentRichText } from "./ContentRichText";

type ContentCardGridProps = {
  items: ContentCard[];
  columns?: 2 | 3;
  className?: string;
};

export function ContentCardGrid({ items, columns = 3, className }: ContentCardGridProps) {
  return (
    <div
      className={cn(
        "grid gap-4",
        columns === 3 ? "md:grid-cols-2 xl:grid-cols-3" : "md:grid-cols-2",
        className,
      )}
    >
      {items.map((item) => (
        <article
          key={item.id}
          className="rounded-jp-lg border border-jp-border bg-jp-surface p-jp-lg shadow-jp-card"
        >
          {item.title ? <h3 className="text-jp-md font-semibold text-jp-text">{item.title}</h3> : null}
          {item.body ? (
            <div className={item.title ? "mt-3" : undefined}>
              <ContentRichText body={item.body} format={item.format} />
            </div>
          ) : null}
        </article>
      ))}
    </div>
  );
}
