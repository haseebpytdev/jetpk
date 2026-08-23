"use client";

import { useEffect, useState } from "react";
import { loadMediaLibrary, uploadMediaLibraryFile } from "@/services/operational-api";

type MediaItem = {
  id: string | number;
  url?: string;
  alt_text?: string;
  original_name?: string;
  name?: string;
  mime_type?: string;
  type?: string;
  width?: number;
  height?: number;
  dimensions?: string;
};

type Props = {
  open: boolean;
  title: string;
  onClose: () => void;
  onSelect: (item: MediaItem) => void;
  onUploadFile?: (file: File) => Promise<void>;
};

export function CmsMediaPickerDialog({ open, title, onClose, onSelect, onUploadFile }: Props) {
  const [items, setItems] = useState<MediaItem[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    if (!open) return;
    setBusy(true);
    void loadMediaLibrary().then((result) => {
      setBusy(false);
      if (!result.ok) {
        setError(result.message ?? "Could not load media library.");
        return;
      }
      const payload = ("data" in result ? result.data : result) as { media?: MediaItem[] };
      setItems(Array.isArray(payload.media) ? payload.media : []);
    });
  }, [open]);

  if (!open) return null;

  return (
    <div
      className="fixed inset-0 z-50 flex items-end justify-center bg-black/40 p-4 sm:items-center"
      role="dialog"
      aria-modal="true"
      aria-label={title}
      data-testid="cms-media-picker"
    >
      <div className="max-h-[85vh] w-full max-w-3xl overflow-auto rounded-xl bg-white p-4 shadow-xl">
        <div className="flex items-start justify-between gap-3">
          <div>
            <h2 className="text-sm font-semibold text-jp-ink">{title}</h2>
            <p className="mt-1 text-xs text-jp-muted">Select existing media or upload a new image. No raw filesystem paths required.</p>
          </div>
          <button type="button" className="rounded-lg border border-jp-border px-2 py-1 text-xs" onClick={onClose}>
            Close
          </button>
        </div>

        {error ? <p className="mt-3 text-sm text-red-600">{error}</p> : null}

        <label className="mt-3 inline-flex min-h-11 cursor-pointer items-center rounded-xl border border-jp-border px-3 text-sm">
          Upload new
          <input
            type="file"
            accept="image/jpeg,image/png,image/webp"
            className="sr-only"
            onChange={(e) => {
              const file = e.target.files?.[0];
              if (!file) return;
              void (async () => {
                setBusy(true);
                setError(null);
                try {
                  if (onUploadFile) {
                    await onUploadFile(file);
                  } else {
                    const formData = new FormData();
                    formData.set("file", file);
                    formData.set("alt_text", file.name);
                    const uploaded = await uploadMediaLibraryFile(formData);
                    if (!uploaded.ok) {
                      setError(uploaded.message ?? "Upload failed");
                      return;
                    }
                  }
                  const refreshed = await loadMediaLibrary();
                  if (refreshed.ok) {
                    const payload = ("data" in refreshed ? refreshed.data : refreshed) as { media?: MediaItem[] };
                    setItems(Array.isArray(payload.media) ? payload.media : []);
                  }
                } finally {
                  setBusy(false);
                }
              })();
            }}
          />
        </label>

        {busy ? <p className="mt-3 text-xs text-jp-muted">Loading…</p> : null}

        <ul className="mt-4 grid gap-3 sm:grid-cols-3">
          {items.map((item) => (
            <li key={String(item.id)} className="rounded-lg border border-jp-border p-2">
              <div className="aspect-[4/3] overflow-hidden rounded-md bg-jp-page">
                {item.url ? (
                  // eslint-disable-next-line @next/next/no-img-element
                  <img src={item.url} alt={item.alt_text || item.name || "Media"} className="h-full w-full object-cover" />
                ) : (
                  <div className="flex h-full items-center justify-center text-xs text-jp-muted">No preview</div>
                )}
              </div>
              <p className="mt-2 truncate text-xs font-medium">{item.original_name || item.name || `Media #${item.id}`}</p>
              <p className="truncate text-[11px] text-jp-muted">
                {item.mime_type || item.type || "image"}
                {item.dimensions
                  ? ` · ${item.dimensions}`
                  : item.width && item.height
                    ? ` · ${item.width}×${item.height}`
                    : ""}
              </p>
              {item.alt_text ? <p className="mt-1 truncate text-[11px] text-jp-muted">Alt: {item.alt_text}</p> : null}
              <button
                type="button"
                className="mt-2 min-h-9 w-full rounded-lg bg-jp-accent px-2 text-xs text-white"
                onClick={() => onSelect(item)}
              >
                Select
              </button>
            </li>
          ))}
        </ul>
        {!busy && items.length === 0 ? (
          <p className="mt-4 text-sm text-jp-muted">No media yet. Upload an image to get started.</p>
        ) : null}
      </div>
    </div>
  );
}
