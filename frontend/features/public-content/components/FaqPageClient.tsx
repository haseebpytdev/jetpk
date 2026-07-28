"use client";

import { useCallback, useId, useMemo, useState } from "react";
import type { FaqCategory } from "../types";
import { EmptyContentState } from "./EmptyContentState";

type FaqPageClientProps = {
  categories: FaqCategory[];
};

export function FaqPageClient({ categories }: FaqPageClientProps) {
  const baseId = useId();
  const [query, setQuery] = useState("");
  const [activeCategory, setActiveCategory] = useState<string>("all");
  const [openItems, setOpenItems] = useState<Record<string, boolean>>({});

  const filteredCategories = useMemo(() => {
    const normalized = query.trim().toLowerCase();
    return categories
      .map((category) => {
        if (activeCategory !== "all" && category.id !== activeCategory) return null;
        const items = category.items.filter((item) => {
          if (!normalized) return true;
          return (
            item.question.toLowerCase().includes(normalized) ||
            item.answer.toLowerCase().includes(normalized)
          );
        });
        if (!items.length) return null;
        return { ...category, items };
      })
      .filter(Boolean) as FaqCategory[];
  }, [activeCategory, categories, query]);

  const toggleItem = useCallback((id: string) => {
    setOpenItems((current) => ({ ...current, [id]: !current[id] }));
  }, []);

  const totalMatches = filteredCategories.reduce((count, category) => count + category.items.length, 0);

  return (
    <div className="space-y-jp-xl" data-testid="faq-page-client">
      <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div className="w-full max-w-md">
          <label htmlFor="faq-search" className="text-jp-sm font-medium text-jp-text">
            Search FAQs
          </label>
          <input
            id="faq-search"
            type="search"
            value={query}
            onChange={(event) => setQuery(event.target.value)}
            placeholder="Search questions"
            className="mt-1 min-h-jp-button w-full rounded-jp-md border border-jp-border px-4 text-jp-sm focus-visible:outline-none focus-visible:shadow-jp-focus"
          />
        </div>

        <div className="flex flex-wrap gap-2" role="tablist" aria-label="FAQ categories">
          <CategoryChip
            active={activeCategory === "all"}
            onClick={() => setActiveCategory("all")}
            label="All"
          />
          {categories.map((category) => (
            <CategoryChip
              key={category.id}
              active={activeCategory === category.id}
              onClick={() => setActiveCategory(category.id)}
              label={category.title}
            />
          ))}
        </div>
      </div>

      {totalMatches === 0 ? (
        <EmptyContentState title="No matching questions" message="Try another keyword or browse all categories." />
      ) : (
        <div className="space-y-jp-xl">
          {filteredCategories.map((category) => (
            <section key={category.id} aria-labelledby={`${baseId}-${category.id}-heading`}>
              <h2 id={`${baseId}-${category.id}-heading`} className="font-display text-jp-h3 font-semibold text-jp-text">
                {category.title}
              </h2>
              <div className="mt-4 space-y-3">
                {category.items.map((item) => {
                  const panelId = `${baseId}-${item.id}-panel`;
                  const expanded = !!openItems[item.id];
                  return (
                    <div key={item.id} id={item.id} className="rounded-jp-lg border border-jp-border bg-jp-surface">
                      <h3>
                        <button
                          type="button"
                          className="flex w-full items-center justify-between gap-4 px-4 py-4 text-left text-jp-sm font-semibold text-jp-text focus-visible:outline-none focus-visible:shadow-jp-focus"
                          aria-expanded={expanded}
                          aria-controls={panelId}
                          onClick={() => toggleItem(item.id)}
                          onKeyDown={(event) => {
                            if (event.key === "Enter" || event.key === " ") {
                              event.preventDefault();
                              toggleItem(item.id);
                            }
                          }}
                        >
                          <span>{item.question}</span>
                          <span aria-hidden="true">{expanded ? "−" : "+"}</span>
                        </button>
                      </h3>
                      <div
                        id={panelId}
                        role="region"
                        hidden={!expanded}
                        className="border-t border-jp-border px-4 py-4 text-jp-sm leading-relaxed text-jp-muted"
                      >
                        {item.answer}
                      </div>
                    </div>
                  );
                })}
              </div>
            </section>
          ))}
        </div>
      )}
    </div>
  );
}

function CategoryChip({ label, active, onClick }: { label: string; active: boolean; onClick: () => void }) {
  return (
    <button
      type="button"
      role="tab"
      aria-selected={active}
      onClick={onClick}
      className={`rounded-full border px-4 py-2 text-jp-sm font-medium focus-visible:outline-none focus-visible:shadow-jp-focus ${
        active ? "border-jp-primary bg-jp-primary-soft text-jp-primary" : "border-jp-border text-jp-muted"
      }`}
    >
      {label}
    </button>
  );
}
