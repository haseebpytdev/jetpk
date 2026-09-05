"use client";

import Link from "next/link";
import { useMemo, useState } from "react";
import { PublicSectionHeader } from "@/features/public-visual";
import type { SupportPageContent } from "../types";
import { ContentCardGrid } from "./ContentCardGrid";
import { EmptyContentState } from "./EmptyContentState";

type SupportTopicSearchProps = {
  topics: SupportPageContent["topics"];
};

export function SupportTopicSearch({ topics }: SupportTopicSearchProps) {
  const [query, setQuery] = useState("");

  const filteredTopics = useMemo(() => {
    const normalized = query.trim().toLowerCase();
    if (!normalized) return topics;
    return topics.filter(
      (topic) =>
        topic.title.toLowerCase().includes(normalized) ||
        topic.summary.toLowerCase().includes(normalized) ||
        topic.keywords.some((keyword) => keyword.includes(normalized)),
    );
  }, [topics, query]);

  return (
    <>
      <div className="mt-6 flex max-w-2xl gap-2">
        <label htmlFor="support-topic-search" className="sr-only">
          Search support topics
        </label>
        <input
          id="support-topic-search"
          type="search"
          value={query}
          onChange={(event) => setQuery(event.target.value)}
          placeholder="Search for help topics, e.g. refund, baggage, visa..."
          className="min-h-jp-button flex-1 rounded-jp-pill border border-jp-border bg-jp-surface px-4 text-jp-sm text-jp-text focus-visible:outline-none focus-visible:shadow-jp-focus"
        />
      </div>
      {filteredTopics.length ? (
        <section className="mt-jp-3xl">
          <PublicSectionHeader title="Explore Support Topics" ctaText="View all topics" ctaUrl="/faq" />
          <div className="mt-jp-lg">
            <ContentCardGrid items={filteredTopics.map((topic) => ({ id: topic.id, title: topic.title, body: topic.summary }))} columns={3} />
          </div>
        </section>
      ) : query ? (
        <EmptyContentState title="No matching topics" message="Try another keyword or contact our team directly." />
      ) : null}
    </>
  );
}

type SupportSignedInBannerProps = {
  href: string;
  label: string | null;
};

export function SupportSignedInBanner({ href, label }: SupportSignedInBannerProps) {
  return (
    <div className="rounded-jp-lg border border-jp-primary/30 bg-jp-primary-soft px-jp-xl py-jp-lg">
      <p className="text-jp-sm font-semibold text-jp-text">You are signed in</p>
      <p className="mt-1 text-jp-sm text-jp-muted">
        For booking help with replies and status tracking, use your account support inbox.
      </p>
      <p className="mt-3">
        <Link
          href={href}
          className="inline-flex min-h-jp-button items-center rounded-jp-button bg-jp-primary px-4 text-jp-sm font-semibold text-white focus-visible:outline-none focus-visible:shadow-jp-focus"
        >
          {label || "Open my support requests"}
        </Link>
      </p>
    </div>
  );
}
