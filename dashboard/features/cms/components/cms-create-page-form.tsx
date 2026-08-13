"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import { createCmsPage } from "@/services/operational-api";

export function CmsCreatePageForm() {
  const router = useRouter();
  const isLive = useDashboardLiveMode();
  const [title, setTitle] = useState("");
  const [slug, setSlug] = useState("");
  const [content, setContent] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  if (!isLive) {
    return null;
  }

  return (
    <section className="space-y-3 rounded-xl border border-jp-border bg-white p-4" data-testid="cms-create-page">
      <h2 className="text-sm font-semibold text-gray-900">Create page</h2>
      {error ? <p className="text-sm text-red-600">{error}</p> : null}
      <label className="block text-xs">Title
        <input className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1" value={title} onChange={(e) => setTitle(e.target.value)} />
      </label>
      <label className="block text-xs">Slug
        <input className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1" placeholder="about-us" value={slug} onChange={(e) => setSlug(e.target.value)} />
      </label>
      <label className="block text-xs">Content
        <textarea className="mt-1 min-h-24 w-full rounded-lg border border-jp-border px-2 py-1" value={content} onChange={(e) => setContent(e.target.value)} />
      </label>
      <button
        type="button"
        className="min-h-11 rounded-xl bg-jp-accent px-3 text-sm text-white disabled:opacity-60"
        disabled={busy || title.trim() === "" || slug.trim() === ""}
        onClick={async () => {
          setBusy(true);
          setError(null);
          const result = await createCmsPage({
            title: title.trim(),
            slug: slug.trim(),
            content,
            status: "draft",
            robots: "index",
          });
          setBusy(false);
          if (!result.ok) {
            setError(result.message ?? "Could not create page");
            return;
          }
          setTitle("");
          setSlug("");
          setContent("");
          router.refresh();
        }}
      >
        {busy ? "Creating…" : "Create draft"}
      </button>
    </section>
  );
}
