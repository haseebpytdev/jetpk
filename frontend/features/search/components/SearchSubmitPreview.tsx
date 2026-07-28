"use client";

import type { GroupSearchDraft, SearchDraft } from "../types";

type SearchSubmitPreviewProps = {
  draft: SearchDraft | GroupSearchDraft | null;
};

export function SearchSubmitPreview({ draft }: SearchSubmitPreviewProps) {
  if (!draft) return null;

  return (
    <div
      className="mt-4 rounded-jp-md border border-jp-primary-border bg-jp-primary-soft px-4 py-3 text-jp-sm text-jp-text"
      role="status"
      aria-live="polite"
      data-testid="search-submit-preview"
    >
      <p className="font-semibold text-jp-primary">Search integration will connect to Laravel in a later phase.</p>
      <p className="mt-1 text-jp-muted">
        Your selections were validated locally. No supplier search was performed.
      </p>
      {process.env.NODE_ENV === "development" ? (
        <pre className="mt-2 max-h-40 overflow-auto rounded-jp-sm bg-white/70 p-2 text-jp-xs text-jp-muted">
          {JSON.stringify(draft, null, 2)}
        </pre>
      ) : null}
    </div>
  );
}
