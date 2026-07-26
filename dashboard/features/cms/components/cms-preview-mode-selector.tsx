"use client";

import { CMS_PREVIEW_MODES } from "@/features/cms/registry/section-registry";
import type { CmsPreviewMode } from "@/types/cms";

type Props = {
  mode: CmsPreviewMode;
  onChange: (mode: CmsPreviewMode) => void;
};

export function CmsPreviewModeSelector({ mode, onChange }: Props) {
  return (
    <fieldset className="space-y-2" data-testid="cms-preview-mode-selector">
      <legend className="text-sm font-semibold text-gray-900">Preview mode</legend>
      <div className="flex flex-wrap gap-2">
        {CMS_PREVIEW_MODES.map((preview) => (
          <button
            key={preview.mode}
            type="button"
            aria-pressed={mode === preview.mode}
            className="min-h-11 rounded-xl border border-jp-border px-3 py-2 text-sm font-medium hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent aria-pressed:border-jp-accent aria-pressed:bg-emerald-50"
            onClick={() => onChange(preview.mode)}
          >
            {preview.label}
          </button>
        ))}
      </div>
    </fieldset>
  );
}
