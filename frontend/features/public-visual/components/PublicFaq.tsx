"use client";

import { useCallback, useId, useState } from "react";
import { cn } from "@/lib/cn";

export type PublicFaqItem = {
  id: string;
  question: string;
  answer: string;
};

type PublicFaqProps = {
  items: PublicFaqItem[];
  className?: string;
  allowMultiple?: boolean;
};

export function PublicFaq({ items, className, allowMultiple = false }: PublicFaqProps) {
  const baseId = useId();
  const [openItems, setOpenItems] = useState<Record<string, boolean>>({});

  const toggleItem = useCallback(
    (id: string) => {
      setOpenItems((current) => {
        if (allowMultiple) {
          return { ...current, [id]: !current[id] };
        }
        const next = !current[id];
        return next ? { [id]: true } : {};
      });
    },
    [allowMultiple],
  );

  if (items.length === 0) return null;

  return (
    <div className={cn("space-y-3", className)} data-testid="public-faq">
      {items.map((item) => {
        const panelId = `${baseId}-${item.id}-panel`;
        const expanded = !!openItems[item.id];
        return (
          <div key={item.id} className="overflow-hidden rounded-jp-lg border border-jp-border bg-jp-surface">
            <h3 className="m-0">
              <button
                type="button"
                className="flex w-full items-center justify-between gap-4 px-4 py-4 text-left text-jp-sm font-semibold text-jp-text transition-colors duration-ui hover:bg-jp-surface-muted focus-visible:outline-none focus-visible:shadow-jp-focus"
                aria-expanded={expanded}
                aria-controls={panelId}
                onClick={() => toggleItem(item.id)}
              >
                <span>{item.question}</span>
                <span aria-hidden="true" className="text-jp-primary">
                  {expanded ? "−" : "+"}
                </span>
              </button>
            </h3>
            <div
              id={panelId}
              role="region"
              hidden={!expanded}
              className="border-t border-jp-border px-4 py-4 text-jp-sm leading-relaxed text-jp-muted motion-reduce:transition-none"
            >
              {item.answer}
            </div>
          </div>
        );
      })}
    </div>
  );
}
