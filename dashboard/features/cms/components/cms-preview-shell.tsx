"use client";

import { CMS_PREVIEW_MODES } from "@/features/cms/registry/section-registry";
import type { CmsPreviewMode } from "@/types/cms";

type Props = {
  mode: CmsPreviewMode;
  label: string;
  children: React.ReactNode;
  warnings?: string[];
  large?: boolean;
};

export function CmsPreviewShell({ mode, label, children, warnings = [], large = false }: Props) {
  const contract = CMS_PREVIEW_MODES.find((m) => m.mode === mode) ?? CMS_PREVIEW_MODES[0];
  const isNight = mode.includes("night");
  const isMobile = mode.includes("mobile");
  const maxWidth = isMobile ? 390 : mode === "tablet" ? 768 : 1280;

  return (
    <div className="space-y-3" data-testid="cms-preview-shell">
      <div
        role="status"
        className="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900"
        aria-live="polite"
      >
        Dashboard preview only — representative layout, not pixel-perfect parity with the live site.
      </div>

      <div
        className="mx-auto w-full overflow-hidden rounded-2xl border border-jp-border shadow-sm"
        style={{ maxWidth }}
        aria-label={`${label} preview frame — ${contract.label}`}
        data-testid="cms-preview-frame"
      >
        <div
          className={`p-4 ${large ? "min-h-[360px]" : "min-h-[200px]"} ${isNight ? "bg-gray-900 text-gray-100" : "bg-white text-gray-900"}`}
          data-theme={isNight ? "night" : "day"}
          data-viewport={mode}
        >
          {children}
        </div>
      </div>

      {warnings.length > 0 ? (
        <ul className="space-y-1 text-sm text-amber-800" data-testid="cms-preview-warnings">
          {warnings.map((w) => (
            <li key={w}>⚠ {w}</li>
          ))}
        </ul>
      ) : null}
    </div>
  );
}
