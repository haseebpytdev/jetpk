"use client";

import Link from "next/link";
import { useMemo, useState } from "react";
import { AnimatedFlightPath } from "@/components/motion/AnimatedFlightPath";
import { PublicSectionHeader } from "@/features/public-visual";
import type { SupportPageContent } from "../types";
import { ContactDetailsCard } from "./ContactDetailsCard";
import { ContactForm } from "./ContactForm";
import { ContentCardGrid } from "./ContentCardGrid";
import { EmptyContentState } from "./EmptyContentState";

type SupportPageClientProps = {
  content: SupportPageContent;
  categories: Array<{ value: string; label: string }>;
  accountSupportHref?: string | null;
  accountSupportLabel?: string | null;
};

export function SupportPageClient({
  content,
  categories,
  accountSupportHref = null,
  accountSupportLabel = null,
}: SupportPageClientProps) {
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

  const faqTeaser = content.faqTeaser;

  return (
    <div className="space-y-jp-3xl">
      {accountSupportHref ? (
        <div className="rounded-jp-lg border border-jp-primary/30 bg-jp-primary-soft px-jp-xl py-jp-lg">
          <p className="text-jp-sm font-semibold text-jp-text">You are signed in</p>
          <p className="mt-1 text-jp-sm text-jp-muted">
            For booking help with replies and status tracking, use your account support inbox.
          </p>
          <p className="mt-3">
            <Link
              href={accountSupportHref}
              className="inline-flex min-h-jp-button items-center rounded-jp-button bg-jp-primary px-4 text-jp-sm font-semibold text-white focus-visible:outline-none focus-visible:shadow-jp-focus"
            >
              {accountSupportLabel || "Open my support requests"}
            </Link>
          </p>
        </div>
      ) : null}
      <div className="grid gap-jp-xl lg:grid-cols-[1.1fr_0.9fr] lg:items-center">
        <div>
          {content.hero.kicker ? (
            <p className="text-jp-xs font-semibold uppercase tracking-[0.16em] text-jp-primary">{content.hero.kicker}</p>
          ) : null}
          <h1 className="mt-3 font-display text-jp-h1 font-bold text-jp-text">
            {content.hero.title || "We're Here to Help"}
          </h1>
          {content.hero.description ? (
            <p className="mt-4 max-w-2xl text-jp-body text-jp-muted">{content.hero.description}</p>
          ) : null}
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
              className="min-h-jp-button flex-1 rounded-jp-pill border border-jp-border bg-jp-surface px-4 text-jp-sm focus-visible:outline-none focus-visible:shadow-jp-focus"
            />
          </div>
        </div>
        <AnimatedFlightPath variant="hero" className="hidden lg:block" />
      </div>

      {filteredTopics.length ? (
        <section>
          <PublicSectionHeader title="Explore Support Topics" ctaText="View all topics" ctaUrl="/faq" />
          <div className="mt-jp-lg">
            <ContentCardGrid items={filteredTopics.map((topic) => ({ id: topic.id, title: topic.title, body: topic.summary }))} columns={3} />
          </div>
        </section>
      ) : query ? (
        <EmptyContentState title="No matching topics" message="Try another keyword or contact our team directly." />
      ) : null}

      <div className="grid gap-jp-xl lg:grid-cols-[1.1fr_0.9fr]">
        {faqTeaser ? (
          <section>
            <PublicSectionHeader title="Frequently Asked Questions" ctaText={faqTeaser.linkLabel} ctaUrl={faqTeaser.linkHref} />
            {faqTeaser.body ? <p className="mt-jp-lg text-jp-sm text-jp-muted">{faqTeaser.body}</p> : null}
            <p className="mt-4">
              <Link href={faqTeaser.linkHref} className="text-jp-sm font-semibold text-jp-primary hover:underline">
                {faqTeaser.linkLabel}
              </Link>
            </p>
          </section>
        ) : null}

        <section className="space-y-jp-lg">
          <PublicSectionHeader title="Contact Us" subtitle="Multiple ways to reach our support team." />
          <ContactDetailsCard contact={content.contact} />
        </section>
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
