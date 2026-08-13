"use client";

import { useEffect, useState } from "react";
import { useDashboardLiveMode } from "@/lib/use-dashboard-live-mode";
import { deleteMediaLibraryItem, loadMediaLibrary, uploadMediaLibraryFile } from "@/services/operational-api";

type MediaRow = {
  id: string;
  file_name: string;
  collection: string;
  alt_text: string;
  mime_type: string;
  url: string | null;
};

export function MediaLibraryPanel() {
  const isLive = useDashboardLiveMode();
  const [rows, setRows] = useState<MediaRow[]>([]);
  const [alt, setAlt] = useState("");
  const [file, setFile] = useState<File | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function refresh() {
    const result = await loadMediaLibrary();
    if (!result.ok) {
      setError(result.message ?? "Could not load media.");
      return;
    }
    const payload = ("data" in result ? result.data : result) as { media?: MediaRow[] };
    setRows(Array.isArray(payload.media) ? payload.media : []);
  }

  useEffect(() => {
    if (!isLive) {
      return;
    }
    void refresh();
  }, [isLive]);

  if (!isLive) {
    return <p className="text-xs text-jp-muted">Media upload is available in live dashboard mode only.</p>;
  }

  return (
    <section className="space-y-3 rounded-xl border border-jp-border bg-white p-4" data-testid="media-library-panel">
      <h2 className="text-sm font-semibold">Media library</h2>
      <p className="text-xs text-jp-muted">Images only (jpg, png, webp, svg, ico). Max 5 MB. Alt text is stored with the file.</p>
      {error ? <p className="text-sm text-red-600">{error}</p> : null}
      <div className="grid gap-2 sm:grid-cols-2">
        <input type="file" accept="image/jpeg,image/png,image/webp,image/svg+xml,image/x-icon" onChange={(e) => setFile(e.target.files?.[0] ?? null)} />
        <input className="rounded-lg border border-jp-border px-2 py-1 text-sm" placeholder="Alt text" value={alt} onChange={(e) => setAlt(e.target.value)} />
      </div>
      <button
        type="button"
        className="min-h-11 rounded-xl bg-jp-accent px-3 text-sm text-white disabled:opacity-60"
        disabled={busy || !file}
        onClick={async () => {
          if (!file) return;
          setBusy(true);
          setError(null);
          const formData = new FormData();
          formData.append("file", file);
          formData.append("collection", "general");
          formData.append("alt_text", alt);
          const result = await uploadMediaLibraryFile(formData);
          setBusy(false);
          if (!result.ok) {
            setError(result.message ?? "Upload failed");
            return;
          }
          setFile(null);
          setAlt("");
          await refresh();
        }}
      >
        {busy ? "Uploading…" : "Upload"}
      </button>
      <ul className="divide-y divide-jp-border rounded-lg border border-jp-border">
        {rows.map((row) => (
          <li key={row.id} className="flex items-center justify-between gap-2 p-2 text-sm">
            <div>
              <p className="font-medium">{row.file_name}</p>
              <p className="text-xs text-jp-muted">{row.collection} · {row.alt_text || "no alt"}</p>
            </div>
            <button
              type="button"
              className="text-xs text-red-700 underline"
              disabled={busy}
              onClick={async () => {
                setBusy(true);
                const result = await deleteMediaLibraryItem(row.id);
                setBusy(false);
                if (!result.ok) {
                  setError(result.message ?? "Delete failed");
                  return;
                }
                await refresh();
              }}
            >
              Remove
            </button>
          </li>
        ))}
      </ul>
    </section>
  );
}
