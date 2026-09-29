"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import { archiveCmsPage, destroyCmsPage, updateCmsPage } from "@/services/operational-api";
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
  const pageKey = page.internalId ?? page.id;
  const [title, setTitle] = useState(page.title);
  const [slug, setSlug] = useState(page.slug);
  const [content, setContent] = useState(typeof page.content === "string" ? page.content : "");
  const [excerpt, setExcerpt] = useState(page.excerpt ?? "");
  const [seoTitle, setSeoTitle] = useState(page.seoTitle ?? "");
  const [seoDescription, setSeoDescription] = useState(page.seoDescription ?? "");
  const [status, setStatus] = useState<"draft" | "active" | "archived">(toLaravelStatus(page.status));
  const [robots, setRobots] = useState(page.robots === "noindex" ? "noindex" : "index");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  if (!isLive) {
    return (
      <p className="text-sm text-amber-700" data-testid="cms-page-local-editor-offline">
        Live mode required to edit CMS pages.
      </p>
    );
  }

  if (!pageKey) {
    return <p className="text-sm text-red-600">This page is missing an editable id.</p>;
  }

  async function save() {
    setBusy(true);
    setError(null);
    setSuccess(null);
    const result = await updateCmsPage(String(pageKey), {
      title: title.trim(),
      slug: slug.trim(),
      content,
      excerpt: excerpt.trim() === "" ? null : excerpt.trim(),
      seo_title: seoTitle.trim() === "" ? null : seoTitle.trim(),
      seo_description: seoDescription.trim() === "" ? null : seoDescription.trim(),
      status,
      robots,
    });
    setBusy(false);
    if (!result.ok) {
      setError(result.message ?? "Could not save page");
      return;
    }
    setSuccess(status === "active" ? "Page published." : "Draft saved.");
    router.refresh();
  }

  return (
    <section className="space-y-3 rounded-xl border border-jp-border bg-white p-4" data-testid="cms-page-local-editor">
      <h3 className="text-sm font-semibold text-gray-900">Edit page</h3>
      {error ? <p className="text-sm text-red-600">{error}</p> : null}
      {success ? <p className="text-sm text-emerald-700">{success}</p> : null}
      <label className="block text-xs font-medium text-jp-muted">
        Title
        <input className="mt-1 w-full rounded-lg border border-jp-border p-2 text-sm" value={title} onChange={(e) => setTitle(e.target.value)} disabled={busy} />
      </label>
      <label className="block text-xs font-medium text-jp-muted">
        Slug
        <input className="mt-1 w-full rounded-lg border border-jp-border p-2 text-sm" value={slug} onChange={(e) => setSlug(e.target.value)} disabled={busy} />
      </label>
      <label className="block text-xs font-medium text-jp-muted">
        Content
        <textarea className="mt-1 min-h-32 w-full rounded-lg border border-jp-border p-2 text-sm" value={content} onChange={(e) => setContent(e.target.value)} disabled={busy} />
      </label>
      <label className="block text-xs font-medium text-jp-muted">
        Excerpt
        <textarea className="mt-1 min-h-16 w-full rounded-lg border border-jp-border p-2 text-sm" value={excerpt} onChange={(e) => setExcerpt(e.target.value)} disabled={busy} />
      </label>
      <div className="grid gap-3 sm:grid-cols-2">
        <label className="block text-xs font-medium text-jp-muted">
          SEO title
          <input className="mt-1 w-full rounded-lg border border-jp-border p-2 text-sm" value={seoTitle} onChange={(e) => setSeoTitle(e.target.value)} disabled={busy} />
        </label>
        <label className="block text-xs font-medium text-jp-muted">
          Robots
          <select className="mt-1 w-full rounded-lg border border-jp-border p-2 text-sm" value={robots} onChange={(e) => setRobots(e.target.value === "noindex" ? "noindex" : "index")} disabled={busy}>
            <option value="index">index</option>
            <option value="noindex">noindex</option>
          </select>
        </label>
      </div>
      <label className="block text-xs font-medium text-jp-muted">
        SEO description
        <textarea className="mt-1 min-h-16 w-full rounded-lg border border-jp-border p-2 text-sm" value={seoDescription} onChange={(e) => setSeoDescription(e.target.value)} disabled={busy} />
      </label>
      <label className="block text-xs font-medium text-jp-muted">
        Status
        <select className="mt-1 w-full rounded-lg border border-jp-border p-2 text-sm" value={status} onChange={(e) => setStatus(e.target.value as "draft" | "active" | "archived")} disabled={busy}>
          <option value="draft">draft</option>
          <option value="active">published (active)</option>
          <option value="archived">archived</option>
        </select>
      </label>
      <div className="flex flex-wrap gap-2">
        <button type="button" className="min-h-11 rounded-xl bg-jp-accent px-4 text-sm text-white disabled:opacity-60" disabled={busy || title.trim() === "" || slug.trim() === ""} onClick={() => void save()}>
          {busy ? "Saving..." : status === "active" ? "Save and publish" : "Save draft"}
        </button>
        <button type="button" className="min-h-11 rounded-xl border border-jp-border px-4 text-sm disabled:opacity-60" disabled={busy} onClick={async () => {
          setBusy(true); setError(null);
          const result = await archiveCmsPage(String(pageKey));
          setBusy(false);
          if (!result.ok) { setError(result.message ?? "Could not archive"); return; }
          setStatus("archived"); setSuccess("Page archived."); router.refresh();
        }}>Archive</button>
        <button type="button" className="min-h-11 rounded-xl border border-red-300 px-4 text-sm text-red-700 disabled:opacity-60" disabled={busy} onClick={async () => {
          if (!window.confirm("Delete this CMS page permanently?")) return;
          setBusy(true); setError(null);
          const result = await destroyCmsPage(String(pageKey));
          setBusy(false);
          if (!result.ok) { setError(result.message ?? "Could not delete"); return; }
          router.refresh();
        }}>Delete</button>
      </div>
    </section>
  );
}