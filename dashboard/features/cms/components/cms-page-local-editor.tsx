"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import { archiveCmsPage, updateCmsPage } from "@/services/operational-api";
import type { CmsPage } from "@/types/cms";

function toLaravelStatus(status: CmsPage["status"]): "draft" | "active" | "archived" {
  if (status === "published" || status === "approved") {
    return "active";
  }
  if (status === "archived") {
    return "archived";
  }
  return "draft";
}

export function CmsPageLocalEditor({ page }: { page: CmsPage }) {
  const router = useRouter();
  const isLive = useDashboardLiveMode();
  const pageKey = page.internalId ?? "";
  const [title, setTitle] = useState(page.title);
  const [slug, setSlug] = useState(page.slug);
  const [content, setContent] = useState(page.content ?? "");
  const [excerpt, setExcerpt] = useState(page.excerpt ?? "");
  const [seoTitle, setSeoTitle] = useState(page.seoTitle);
  const [seoDescription, setSeoDescription] = useState(page.seoDescription);
  const [status, setStatus] = useState<"draft" | "active" | "archived">(toLaravelStatus(page.status));
  const [robots, setRobots] = useState(page.robots === "noindex" ? "noindex" : "index");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  if (!pageKey) {
    return (
      <p className="text-xs text-jp-muted" data-testid="cms-page-editor-unavailable">
        This CMS page cannot be edited from the dashboard yet (missing internal id).
      </p>
    );
  }

  if (!isLive) {
    return (
      <p className="text-xs text-jp-muted" data-testid="cms-page-editor-preview">
        CMS page editing is available in live dashboard mode only.
      </p>
    );
  }

  async function handleSave() {
    setBusy(true);
    setError(null);
    setSuccess(null);
    const result = await updateCmsPage(pageKey, {
      title: title.trim(),
      slug: slug.trim(),
      content,
      excerpt: excerpt.trim() || null,
      seo_title: seoTitle.trim() || null,
      seo_description: seoDescription.trim() || null,
      robots,
      status,
      show_in_footer: Boolean(page.showInFooter),
      footer_group: page.footerGroup,
      footer_label: page.footerLabel,
    });
    setBusy(false);
    if (!result.ok) {
      setError(result.message ?? "CMS page update failed.");
      return;
    }
    setSuccess("CMS page saved.");
    router.refresh();
  }

  async function handleArchive() {
    setBusy(true);
    setError(null);
    setSuccess(null);
    const result = await archiveCmsPage(pageKey);
    setBusy(false);
    if (!result.ok) {
      setError(result.message ?? "Archive failed.");
      return;
    }
    setStatus("archived");
    setSuccess("CMS page archived.");
    router.refresh();
  }

  return (
    <div className="space-y-3 rounded-xl border border-jp-border bg-gray-50 p-4" data-testid="cms-page-local-editor">
      <div>
        <h3 className="text-sm font-semibold text-gray-900">Edit CMS page</h3>
        <p className="mt-1 text-xs text-jp-muted">
          Operational content, SEO, draft/publish, and preview for JetPakistan pages.
        </p>
      </div>
      <div className="grid gap-3 sm:grid-cols-2">
        <label className="block text-sm sm:col-span-2">
          <span className="text-jp-muted">Title</span>
          <input
            className="mt-1 w-full rounded-xl border border-jp-border bg-white px-3 py-2"
            value={title}
            disabled={busy}
            onChange={(e) => setTitle(e.target.value)}
            data-testid="cms-page-title"
          />
        </label>
        <label className="block text-sm">
          <span className="text-jp-muted">Slug</span>
          <input
            className="mt-1 w-full rounded-xl border border-jp-border bg-white px-3 py-2"
            value={slug}
            disabled={busy}
            onChange={(e) => setSlug(e.target.value)}
            data-testid="cms-page-slug"
          />
        </label>
        <label className="block text-sm">
          <span className="text-jp-muted">Status</span>
          <select
            className="mt-1 w-full rounded-xl border border-jp-border bg-white px-3 py-2"
            value={status}
            disabled={busy}
            onChange={(e) => setStatus(e.target.value as "draft" | "active" | "archived")}
            data-testid="cms-page-status"
          >
            <option value="draft">Draft</option>
            <option value="active">Active / published</option>
            <option value="archived">Archived</option>
          </select>
        </label>
        <label className="block text-sm sm:col-span-2">
          <span className="text-jp-muted">Excerpt</span>
          <input
            className="mt-1 w-full rounded-xl border border-jp-border bg-white px-3 py-2"
            value={excerpt}
            disabled={busy}
            onChange={(e) => setExcerpt(e.target.value)}
            data-testid="cms-page-excerpt"
          />
        </label>
        <label className="block text-sm sm:col-span-2">
          <span className="text-jp-muted">Content (HTML)</span>
          <textarea
            className="mt-1 min-h-40 w-full rounded-xl border border-jp-border bg-white px-3 py-2 font-mono text-xs"
            value={content}
            disabled={busy}
            onChange={(e) => setContent(e.target.value)}
            data-testid="cms-page-content"
          />
        </label>
        <label className="block text-sm">
          <span className="text-jp-muted">SEO title</span>
          <input
            className="mt-1 w-full rounded-xl border border-jp-border bg-white px-3 py-2"
            value={seoTitle}
            disabled={busy}
            onChange={(e) => setSeoTitle(e.target.value)}
            data-testid="cms-page-seo-title"
          />
        </label>
        <label className="block text-sm">
          <span className="text-jp-muted">Robots</span>
          <select
            className="mt-1 w-full rounded-xl border border-jp-border bg-white px-3 py-2"
            value={robots}
            disabled={busy}
            onChange={(e) => setRobots(e.target.value)}
            data-testid="cms-page-robots"
          >
            <option value="index">index</option>
            <option value="noindex">noindex</option>
          </select>
        </label>
        <label className="block text-sm sm:col-span-2">
          <span className="text-jp-muted">SEO description</span>
          <textarea
            className="mt-1 min-h-20 w-full rounded-xl border border-jp-border bg-white px-3 py-2"
            value={seoDescription}
            disabled={busy}
            onChange={(e) => setSeoDescription(e.target.value)}
            data-testid="cms-page-seo-description"
          />
        </label>
      </div>
      {error ? (
        <p className="text-sm text-red-700" data-testid="cms-page-editor-error">
          {error}
        </p>
      ) : null}
      {success ? (
        <p className="text-sm text-emerald-700" data-testid="cms-page-editor-success">
          {success}
        </p>
      ) : null}
      <div className="flex flex-wrap gap-2">
        <a
          className="inline-flex min-h-11 items-center rounded-xl border border-jp-border bg-white px-4 py-2 text-sm font-medium text-gray-900"
          href={slug ? `/pages/${encodeURIComponent(slug)}` : "/"}
          target="_blank"
          rel="noreferrer"
        >
          Preview
        </a>
        <button
          {busy ? "Saving…" : "Save page"}
        </button>
        <button
          type="button"
          className="inline-flex min-h-11 items-center rounded-xl border border-jp-border bg-white px-4 py-2 text-sm font-medium text-gray-900 disabled:opacity-50"
          disabled={busy || status === "archived"}
          onClick={() => void handleArchive()}
          data-testid="cms-page-archive"
        >
          Archive
        </button>
      </div>
    </div>
  );
}
