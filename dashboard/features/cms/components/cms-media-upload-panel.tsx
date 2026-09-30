"use client";

import { useRef, useState } from "react";
import { Button } from "@/components/ui/button";
import { laravelRequest } from "@/lib/api/laravel-action-client";
import { agencyMediaDestroyPath, agencyMediaStorePath } from "@/lib/api/portal-paths";

type Props = {
  onUploaded?: () => void;
};

export function CmsMediaUploadPanel({ onUploaded }: Props) {
  const inputRef = useRef<HTMLInputElement | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [lastUploadedId, setLastUploadedId] = useState<string | null>(null);

  async function onUpload(file: File) {
    setBusy(true);
    setError(null);
    setSuccess(null);
    const form = new FormData();
    form.append("file", file);
    form.append("collection", "general");
    const result = await laravelRequest<{ asset?: { id?: string; file_name?: string } }>(agencyMediaStorePath(), {
      method: "POST",
      formData: form,
      headers: { Accept: "application/json" },
      retryCsrfOnce: true,
    });
    setBusy(false);
    if (!result.ok) {
      setError(result.message ?? "Upload failed.");
      return;
    }
    const assetId = String(result.data.asset?.id ?? "");
    setLastUploadedId(assetId || null);
    setSuccess(`Uploaded ${result.data.asset?.file_name ?? "media asset"}.`);
    onUploaded?.();
  }

  async function cleanupLastUpload() {
    if (!lastUploadedId) return;
    setBusy(true);
    const result = await laravelRequest(agencyMediaDestroyPath(lastUploadedId), {
      method: "DELETE",
      headers: { Accept: "application/json" },
      retryCsrfOnce: true,
    });
    setBusy(false);
    if (!result.ok) {
      setError(result.message ?? "Cleanup delete failed.");
      return;
    }
    setLastUploadedId(null);
    setSuccess("QA upload removed.");
    onUploaded?.();
  }

  return (
    <section className="rounded-xl border border-jp-border bg-white p-4" data-testid="cms-media-upload-panel">
      <h3 className="text-sm font-semibold text-gray-900">Media library upload</h3>
      <p className="mt-1 text-sm text-jp-muted">
        Upload controlled QA media through the authoritative Laravel media store. Use cleanup to remove the last QA upload.
      </p>
      <div className="mt-3 flex flex-wrap gap-2">
        <input
          ref={inputRef}
          type="file"
          accept="image/*"
          className="hidden"
          data-testid="cms-media-upload-input"
          onChange={(event) => {
            const file = event.target.files?.[0];
            if (file) void onUpload(file);
            event.currentTarget.value = "";
          }}
        />
        <Button type="button" size="sm" disabled={busy} onClick={() => inputRef.current?.click()} data-testid="cms-media-upload-button">
          {busy ? "Uploading…" : "Upload QA media"}
        </Button>
        {lastUploadedId ? (
          <Button type="button" size="sm" variant="secondary" disabled={busy} onClick={() => void cleanupLastUpload()} data-testid="cms-media-upload-cleanup">
            Remove last QA upload
          </Button>
        ) : null}
      </div>
      {error ? (
        <p className="mt-2 text-sm text-red-700" role="alert">
          {error}
        </p>
      ) : null}
      {success ? (
        <p className="mt-2 text-sm text-emerald-700" role="status">
          {success}
        </p>
      ) : null}
    </section>
  );
}
