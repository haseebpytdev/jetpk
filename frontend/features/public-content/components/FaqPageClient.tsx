"use client";

import { PublicFaq, type PublicFaqItem } from "@/features/public-visual";
import { useMemo, useState } from "react";
import type { FaqCategory } from "../types";
import { EmptyContentState } from "./EmptyContentState";

type FaqPageClientProps = {
  categories: FaqCategory[];
};

export function FaqPageClient({ categories }: FaqPageClientProps) {
  const [query, setQuery] = useState("");
  const [activeCategory, setActiveCategory] = useState<string>("all");

  const allItems: PublicFaqItem[] = useMemo(
    () =>
      categories.flatMap((category) =>
        category.items.map((item) => ({
          id: item.id,
          question: item.question,
          answer: item.answer,
        })),
      ),
    [categories],
  );

  const filteredItems = useMemo(() => {
    const normalized = query.trim().toLowerCase();
    return allItems.filter((item) => {
      const category = categories.find((cat) => cat.items.some((q) => q.id === item.id));
      if (activeCategory !== "all" && category?.id !== activeCategory) return false;
      if (!normalized) return true;
      return item.question.toLowerCase().includes(normalized) || item.answer.toLowerCase().includes(normalized);
    });
  }, [activeCategory, allItems, categories, query]);

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
          <CategoryChip active={activeCategory === "all"} onClick={() => setActiveCategory("all")} label="All" />
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

      {filteredItems.length === 0 ? (
        <EmptyContentState title="No matching questions" message="Try another keyword or browse all categories." />
      ) : (
        <PublicFaq items={filteredItems} allowMultiple />
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
