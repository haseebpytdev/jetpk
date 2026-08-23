"use client";

import { useEffect, useState } from "react";
import { DashboardLink as Link } from "@/components/dashboard/dashboard-link";
import {
  loadPageSettings,
  loadPageSettingsIndex,
  publishPageSettings,
  savePageSettings,
} from "@/services/operational-api";

type ManagedPage = {
  key: string;
  label: string;
  status?: string;
  public_path?: string;
};

const FALLBACK_PAGES: ManagedPage[] = [
  { key: "home", label: "Homepage", public_path: "/" },
  { key: "about", label: "About", public_path: "/about-us" },
  { key: "faq", label: "FAQ", public_path: "/faq" },
  { key: "support", label: "Support / Contact", public_path: "/support" },
  { key: "terms", label: "Terms", public_path: "/terms" },
  { key: "privacy", label: "Privacy", public_path: "/privacy" },
];

export function PageSettingsPagesPanel() {
  const [pages, setPages] = useState<ManagedPage[]>(FALLBACK_PAGES);
  const [selected, setSelected] = useState<string | null>(null);
  const [content, setContent] = useState<Record<string, unknown>>({});
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  useEffect(() => {
    void loadPageSettingsIndex().then((result) => {
      if (!result.ok) return;
      const payload = ("data" in result ? result.data : result) as { pages?: ManagedPage[] };
      if (Array.isArray(payload.pages) && payload.pages.length > 0) {
        setPages(payload.pages.map((p) => ({
          key: String(p.key ?? p.page_key ?? ""),
          label: String(p.label ?? p.title ?? p.key ?? ""),
          status: typeof p.status === "string" ? p.status : undefined,
          public_path: typeof p.public_path === "string" ? p.public_path : undefined,
        })).filter((p) => p.key));
      }
    });
  }, []);

  async function openEditor(pageKey: string) {
    setSelected(pageKey);
    setBusy(true);
    setError(null);
    const result = await loadPageSettings(pageKey);
    setBusy(false);
    if (!result.ok) {
      setError(result.message ?? "Could not load page");
      return;
    }
    const payload = ("data" in result ? result.data : result) as { content?: Record<string, unknown> };
    setContent(payload.content ?? {});
  }

  async function saveDraft() {
    if (!selected) return;
    setBusy(true);
    const result = await savePageSettings(selected, content);
    setBusy(false);
    if (!result.ok) {
      setError(result.message ?? "Save failed");
      return;
    }
    setSuccess("Draft saved.");
  }

  async function publish() {
    if (!selected) return;
    setBusy(true);
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
  }

  return (
    <section className="mt-4 space-y-4 rounded-xl border border-jp-border bg-white p-4" data-testid="cms-pages-existing">
      <div>
        <h2 className="text-sm font-semibold">Managed pages</h2>
        <p className="text-xs text-jp-muted">
          Edit existing JetPakistan informational pages without creating duplicates. Homepage structured media lives under Homepage.
        </p>
      </div>
      {error ? <p className="text-sm text-red-600">{error}</p> : null}
      {success ? <p className="text-sm text-emerald-700">{success}</p> : null}

      <div className="overflow-x-auto">
        <table className="min-w-full text-left text-sm">
          <thead>
            <tr className="border-b border-jp-border text-xs uppercase tracking-wide text-jp-muted">
              <th className="px-2 py-2">Page</th>
              <th className="px-2 py-2">Status</th>
              <th className="px-2 py-2">Actions</th>
            </tr>
          </thead>
          <tbody>
            {pages.map((page) => (
              <tr key={page.key} className="border-b border-jp-border/70">
                <td className="px-2 py-3 font-medium">{page.label}</td>
                <td className="px-2 py-3 text-xs text-jp-muted">{page.status ?? "managed"}</td>
                <td className="px-2 py-3">
                  <div className="flex flex-wrap gap-2">
                    <button type="button" className="rounded-lg border border-jp-border px-2 py-1 text-xs" onClick={() => void openEditor(page.key)}>
                      Edit
                    </button>
                    {page.public_path ? (
                      <a className="rounded-lg border border-jp-border px-2 py-1 text-xs" href={page.public_path} target="_blank" rel="noreferrer">
                        Preview
                      </a>
                    ) : null}
                    <button
                      type="button"
                      className="rounded-lg border border-jp-border px-2 py-1 text-xs"
                      onClick={() => {
                        void openEditor(page.key).then(async () => {
                          await publishPageSettings(page.key);
                          setSuccess(`${page.label} publish requested.`);
                        });
                      }}
                    >
                      Publish
                    </button>
                    {page.key === "home" ? (
                      <Link href="/cms/sections" className="rounded-lg border border-jp-border px-2 py-1 text-xs">
                        Open builder
                      </Link>
                    ) : null}
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {selected ? (
        <div className="rounded-lg border border-jp-border p-3" data-testid="cms-page-editor">
          <h3 className="text-sm font-semibold">Editing: {selected}</h3>
          <p className="mt-1 text-xs text-jp-muted">Page information / SEO / content JSON. Structured blocks remain schema-backed.</p>
          <textarea
            className="mt-3 min-h-48 w-full rounded-lg border border-jp-border p-2 font-mono text-xs"
            value={JSON.stringify(content, null, 2)}
            onChange={(e) => {
              try {
                setContent(JSON.parse(e.target.value) as Record<string, unknown>);
                setError(null);
              } catch {
                setError("Content must be valid JSON while this interim editor is open.");
              }
            }}
          />
          <div className="mt-3 flex flex-wrap gap-2">
            <button type="button" className="min-h-11 rounded-xl bg-jp-accent px-3 text-sm text-white disabled:opacity-60" disabled={busy} onClick={() => void saveDraft()}>
              Save draft
            </button>
            <button type="button" className="min-h-11 rounded-xl border border-jp-border px-3 text-sm disabled:opacity-60" disabled={busy} onClick={() => void publish()}>
              Publish
            </button>
            <button type="button" className="min-h-11 rounded-xl border border-jp-border px-3 text-sm" onClick={() => setSelected(null)}>
              Close
            </button>
          </div>
        </div>
      ) : null}
    </section>
  );
}
