"use client";

import { useCallback, useEffect, useState } from "react";
import { DashboardLink as Link } from "@/components/dashboard/dashboard-link";
import { CmsCreatePageForm } from "@/features/cms/components/cms-create-page-form";
import { StructuredPageSettingsEditor } from "@/features/cms/components/structured-page-settings-editor";
import {
  beginPageSettingsPreview,
  duplicatePageSettings,
  loadPageSettings,
  loadPageSettingsIndex,
  publishPageSettings,
  savePageSettings,
  unpublishPageSettings,
} from "@/services/operational-api";

type ManagedPage = {
  key: string;
  label: string;
  status?: string;
  route?: string;
  public_path?: string;
  published?: boolean;
  draft?: boolean;
  archived?: boolean;
};

type SectionDef = { key: string; label: string; fields: string[] };

type EditPayload = {
  content?: Record<string, unknown>;
  draft?: Record<string, unknown> | null;
  sections?: SectionDef[];
  publishing?: {
    has_draft?: boolean;
    has_published?: boolean;
    archived?: boolean;
    can_unpublish?: boolean;
  };
  preview_url?: string;
  previewUrl?: string;
};

const FALLBACK_PAGES: ManagedPage[] = [
  { key: "home", label: "Homepage", public_path: "/" },
  { key: "about", label: "About", public_path: "/about-us" },
  { key: "faq", label: "FAQ", public_path: "/faq" },
  { key: "support", label: "Support / Contact", public_path: "/support" },
  { key: "terms", label: "Terms", public_path: "/terms" },
  { key: "privacy", label: "Privacy", public_path: "/privacy" },
];

function unwrapData<T extends Record<string, unknown>>(result: { ok: boolean; data?: T } & Record<string, unknown>): T {
  if ("data" in result && result.data && typeof result.data === "object") {
    return result.data as T;
  }
  return result as unknown as T;
}

export function PageSettingsPagesPanel() {
  const [pages, setPages] = useState<ManagedPage[]>(FALLBACK_PAGES);
  const [selected, setSelected] = useState<string | null>(null);
  const [content, setContent] = useState<Record<string, unknown>>({});
  const [sections, setSections] = useState<SectionDef[]>([]);
  const [canUnpublish, setCanUnpublish] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const refreshCatalog = useCallback(async () => {
    const result = await loadPageSettingsIndex();
    if (!result.ok) return;
    const payload = unwrapData<{ pages?: ManagedPage[] }>(result as { ok: boolean; data?: { pages?: ManagedPage[] } });
    if (Array.isArray(payload.pages) && payload.pages.length > 0) {
      setPages(
        payload.pages
          .map((p) => ({
            key: String(p.key ?? ""),
            label: String(p.label ?? p.key ?? ""),
            status: typeof p.status === "string" ? p.status : undefined,
            route: typeof p.route === "string" ? p.route : undefined,
            public_path: typeof p.public_path === "string" ? p.public_path : typeof p.route === "string" ? p.route : undefined,
            published: Boolean(p.published),
            draft: Boolean(p.draft),
            archived: Boolean(p.archived),
          }))
          .filter((p) => p.key),
      );
    }
  }, []);

  useEffect(() => {
    void refreshCatalog();
  }, [refreshCatalog]);

  async function openEditor(pageKey: string) {
    if (pageKey === "home") return;
    setSelected(pageKey);
    setBusy(true);
    setError(null);
    setSuccess(null);
    const result = await loadPageSettings(pageKey);
    setBusy(false);
    if (!result.ok) {
      setError(result.message ?? "Could not load page");
      return;
    }
    const payload = unwrapData<EditPayload>(result as { ok: boolean; data?: EditPayload });
    setContent(payload.draft ?? payload.content ?? {});
    setSections(Array.isArray(payload.sections) ? payload.sections : []);
    setCanUnpublish(Boolean(payload.publishing?.can_unpublish));
  }

  async function saveDraft() {
    if (!selected) return;
    setBusy(true);
    setError(null);
    const result = await savePageSettings(selected, content);
    setBusy(false);
    if (!result.ok) {
      setError(result.message ?? "Save failed");
      return;
    }
    setSuccess("Draft saved. Public production content unchanged until Publish.");
    await refreshCatalog();
  }

  async function publish() {
    if (!selected) return;
    setBusy(true);
    setError(null);
    const saved = await savePageSettings(selected, content);
    if (!saved.ok) {
      setBusy(false);
      setError(saved.message ?? "Save failed");
      return;
    }
    const published = await publishPageSettings(selected);
    setBusy(false);
    if (!published.ok) {
      setError(published.message ?? "Publish failed");
      return;
    }
    setSuccess("Page published.");
    await openEditor(selected);
    await refreshCatalog();
  }

  async function previewDraft() {
    if (!selected) return;
    setBusy(true);
    setError(null);
    const saved = await savePageSettings(selected, content);
    if (!saved.ok) {
      setBusy(false);
      setError(saved.message ?? "Save failed before preview");
      return;
    }
    const preview = await beginPageSettingsPreview(selected);
    setBusy(false);
    if (!preview.ok) {
      setError(preview.message ?? "Preview failed");
      return;
    }
    const payload = unwrapData<{ preview_url?: string; previewUrl?: string }>(
      preview as { ok: boolean; data?: { preview_url?: string; previewUrl?: string } },
    );
    const url = payload.preview_url ?? payload.previewUrl;
    if (url) window.open(url, "_blank", "noopener,noreferrer");
  }

  async function archiveSelected() {
    if (!selected || selected === "home") return;
    setBusy(true);
    setError(null);
    const result = await unpublishPageSettings(selected);
    setBusy(false);
    if (!result.ok) {
      setError(result.message ?? "Archive failed");
      return;
    }
    setSuccess("Page unpublished / archived. Record retained.");
    setSelected(null);
    await refreshCatalog();
  }

  async function duplicateRow(pageKey: string) {
    if (pageKey === "home") return;
    setBusy(true);
    setError(null);
    const result = await duplicatePageSettings(pageKey);
    setBusy(false);
    if (!result.ok) {
      setError(result.message ?? "Duplicate failed");
      return;
    }
    setSuccess("Duplicated as draft with a unique slug. Not published.");
    await refreshCatalog();
    const payload = unwrapData<{ page_key?: string }>(result as { ok: boolean; data?: { page_key?: string } });
    if (payload.page_key) {
      await openEditor(payload.page_key);
    }
  }

  return (
    <section className="mt-4 space-y-4 rounded-xl border border-jp-border bg-white p-4" data-testid="cms-pages-existing">
      <div>
        <h2 className="text-sm font-semibold">Managed pages</h2>
        <p className="text-xs text-jp-muted">
          Edit existing JetPakistan informational pages without creating duplicate routes. Homepage structured media lives under Homepage Builder.
        </p>
      </div>
      {error ? <p className="text-sm text-red-600">{error}</p> : null}
      {success ? <p className="text-sm text-emerald-700">{success}</p> : null}

      <div className="overflow-x-auto">
        <table className="min-w-full text-left text-sm">
          <thead>
            <tr className="border-b border-jp-border text-xs uppercase tracking-wide text-jp-muted">
              <th className="px-2 py-2">Page</th>
              <th className="px-2 py-2">Route</th>
              <th className="px-2 py-2">Status</th>
              <th className="px-2 py-2">Actions</th>
            </tr>
          </thead>
          <tbody>
            {pages.map((page) => (
              <tr key={page.key} className="border-b border-jp-border/70">
                <td className="px-2 py-3 font-medium">{page.label}</td>
                <td className="px-2 py-3 text-xs text-jp-muted">{page.public_path ?? page.route ?? "—"}</td>
                <td className="px-2 py-3 text-xs text-jp-muted">
                  {page.key === "home"
                    ? "Homepage Builder"
                    : page.archived
                      ? "Archived"
                      : page.status ?? (page.published ? "Published" : page.draft ? "Draft" : "managed")}
                </td>
                <td className="px-2 py-3">
                  <div className="flex flex-wrap gap-2">
                    {page.key === "home" ? (
                      <Link href="/cms/sections" className="rounded-lg border border-jp-border px-2 py-1 text-xs">
                        Open builder
                      </Link>
                    ) : (
                      <>
                        <button type="button" className="rounded-lg border border-jp-border px-2 py-1 text-xs" onClick={() => void openEditor(page.key)}>
                          Edit
                        </button>
                        <button type="button" className="rounded-lg border border-jp-border px-2 py-1 text-xs" disabled={busy} onClick={() => void duplicateRow(page.key)}>
                          Duplicate
                        </button>
                        {page.published || page.status === "published" || page.status === "draft_ahead" ? (
                          <button
                            type="button"
                            className="rounded-lg border border-red-200 px-2 py-1 text-xs text-red-700"
                            disabled={busy}
                            onClick={() => {
                              void unpublishPageSettings(page.key).then((r) => {
                                if (r.ok) {
                                  setSuccess(`${page.label} archived from public.`);
                                  void refreshCatalog();
                                } else {
                                  setError(r.message ?? "Archive failed");
                                }
                              });
                            }}
                          >
                            Archive
                          </button>
                        ) : null}
                      </>
                    )}
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {selected ? (
        <div className="space-y-3 rounded-lg border border-jp-border p-3" data-testid="cms-page-editor">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <h3 className="text-sm font-semibold">Editing: {selected}</h3>
            <div className="flex flex-wrap gap-2">
              <button type="button" className="min-h-11 rounded-xl bg-jp-accent px-3 text-sm text-white disabled:opacity-60" disabled={busy} onClick={() => void saveDraft()}>
                Save draft
              </button>
              <button type="button" className="min-h-11 rounded-xl border border-jp-border px-3 text-sm disabled:opacity-60" disabled={busy} onClick={() => void previewDraft()}>
                Preview
              </button>
              <button type="button" className="min-h-11 rounded-xl border border-jp-border px-3 text-sm disabled:opacity-60" disabled={busy} onClick={() => void publish()}>
                Publish
              </button>
              {canUnpublish ? (
                <button type="button" className="min-h-11 rounded-xl border border-red-200 px-3 text-sm text-red-700 disabled:opacity-60" disabled={busy} onClick={() => void archiveSelected()}>
                  Archive
                </button>
              ) : null}
              <button type="button" className="min-h-11 rounded-xl border border-jp-border px-3 text-sm" onClick={() => setSelected(null)}>
                Close
              </button>
            </div>
          </div>

          <StructuredPageSettingsEditor
            pageKey={selected}
            content={content}
            sections={sections}
            onChange={setContent}
            onMediaAttached={() => {
              void openEditor(selected);
            }}
            disabled={busy}
          />
        </div>
      ) : null}

      <CmsCreatePageForm />
    </section>
  );
}
