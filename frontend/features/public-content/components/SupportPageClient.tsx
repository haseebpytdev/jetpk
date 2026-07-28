"use client";

import { useMemo, useState } from "react";
import type { SupportPageContent } from "../types";
import { ContactDetailsCard } from "./ContactDetailsCard";
import { ContactForm } from "./ContactForm";
import { ContentCardGrid } from "./ContentCardGrid";
import { EmptyContentState } from "./EmptyContentState";
import Link from "next/link";

type SupportPageClientProps = {
  content: SupportPageContent;
  categories: Array<{ value: string; label: string }>;
};

export function SupportPageClient({ content, categories }: SupportPageClientProps) {
  const [query, setQuery] = useState("");

  const filteredTopics = useMemo(() => {
    const normalized = query.trim().toLowerCase();
    if (!normalized) return content.topics;
    return content.topics.filter(
      (topic) =>
        topic.title.toLowerCase().includes(normalized) ||
        topic.summary.toLowerCase().includes(normalized) ||
        topic.keywords.some((keyword) => keyword.includes(normalized)),
    );
  }, [content.topics, query]);

  return (
    <div className="space-y-jp-2xl">
      <div>
        <label htmlFor="support-topic-search" className="text-jp-sm font-medium text-jp-text">
          Search support topics
        </label>
        <input
          id="support-topic-search"
          type="search"
          value={query}
          onChange={(event) => setQuery(event.target.value)}
          placeholder="e.g. payment, PNR, refund"
          className="mt-1 min-h-jp-button w-full max-w-xl rounded-jp-md border border-jp-border px-4 text-jp-sm focus-visible:outline-none focus-visible:shadow-jp-focus"
        />
      </div>

      {filteredTopics.length ? (
        <ContentCardGrid items={filteredTopics.map((topic) => ({ id: topic.id, title: topic.title, body: topic.summary }))} />
      ) : (
        <EmptyContentState title="No matching topics" message="Try another keyword or contact our team directly." />
      )}

      <ContentCardGrid items={content.departments} columns={3} />

      <div className="grid gap-jp-xl lg:grid-cols-2">
        <ContactDetailsCard contact={content.contact} />

        {content.faqTeaser ? (
          <section className="rounded-jp-lg border border-jp-border bg-jp-surface p-jp-lg shadow-jp-card">
            <h2 className="text-jp-md font-semibold text-jp-text">{content.faqTeaser.title}</h2>
            {content.faqTeaser.body ? <p className="mt-3 text-jp-sm text-jp-muted">{content.faqTeaser.body}</p> : null}
            <p className="mt-4">
              <Link href={content.faqTeaser.linkHref} className="text-jp-sm font-semibold text-jp-primary hover:underline">
                {content.faqTeaser.linkLabel}
              </Link>
            </p>
          </section>
        ) : null}
      </div>

      <section className="rounded-jp-xl border border-jp-border bg-jp-surface p-jp-2xl shadow-jp-card" aria-labelledby="support-form-heading">
        <h2 id="support-form-heading" className="text-jp-h3 font-semibold text-jp-text">
          Submit a support request
        </h2>
        <p className="mt-2 text-jp-sm text-jp-muted">
          Tell us what you need and our team will respond shortly. For urgent booking status, include your booking reference.
        </p>
        <div className="mt-6">
          <ContactForm formType="support" showBookingReference showCategory categories={categories} />
        </div>
      </section>
    </div>
  );
}
