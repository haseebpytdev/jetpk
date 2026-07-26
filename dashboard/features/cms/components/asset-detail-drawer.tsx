"use client";

import { CmsPreviewModeSelector } from "@/features/cms/components/cms-preview-mode-selector";
import { CmsPreviewShell } from "@/features/cms/components/cms-preview-shell";
import { CmsRevisionTimeline } from "@/features/cms/components/cms-revision-timeline";
import { CmsValidationSummary } from "@/features/cms/components/cms-validation-summary";
import { CmsStatusBadge } from "@/components/ui/status-badge";
import { mockCmsBanners, mockCmsPages, mockCmsRevisions, mockCmsSections } from "@/mocks/cms-fixtures";
import { validateAsset } from "@/features/cms/validation/cms-validation";
import { mergeValidationIssues } from "@/features/cms/validation/link-validation";
import type { CmsAsset, CmsPreviewMode } from "@/types/cms";

export function AssetDetailDrawerContent({
  asset,
  previewMode,
  onPreviewModeChange,
}: {
  asset: CmsAsset;
  previewMode: CmsPreviewMode;
  onPreviewModeChange: (mode: CmsPreviewMode) => void;
}) {
  const validation = mergeValidationIssues(validateAsset(asset));
  const usages = [
    ...mockCmsSections.filter((s) => s.assetIds.includes(asset.id)).map((s) => ({ type: "section", id: s.id })),
    ...mockCmsBanners.filter((b) => [b.desktopAssetId, b.mobileAssetId, b.dayAssetId, b.nightAssetId].includes(asset.id)).map((b) => ({ type: "banner", id: b.id })),
    ...mockCmsPages.filter((p) => p.sectionIds.some((sid) => mockCmsSections.find((s) => s.id === sid)?.assetIds.includes(asset.id))).map((p) => ({ type: "page", id: p.id })),
  ];

  return (
    <div className="space-y-4" data-testid="cms-asset-drawer">
      <section>
        <h3 className="text-sm font-semibold text-gray-900">Asset metadata</h3>
        <dl className="mt-2 grid gap-2 text-sm sm:grid-cols-2">
          <div><dt className="text-jp-muted">Asset ID</dt><dd>{asset.id}</dd></div>
          <div><dt className="text-jp-muted">Internal name</dt><dd>{asset.internalName}</dd></div>
          <div><dt className="text-jp-muted">Category</dt><dd>{asset.category}</dd></div>
          <div><dt className="text-jp-muted">File type</dt><dd>{asset.fileType}</dd></div>
          <div><dt className="text-jp-muted">Dimensions</dt><dd>{asset.desktop.width}×{asset.desktop.height}</dd></div>
          <div><dt className="text-jp-muted">Aspect ratio</dt><dd>{asset.desktop.aspectRatio}</dd></div>
          <div><dt className="text-jp-muted">Approval</dt><dd><CmsStatusBadge status={asset.approvalStatus} /></dd></div>
          <div><dt className="text-jp-muted">Usage count</dt><dd>{asset.usageCount}</dd></div>
        </dl>
      </section>

      <section>
        <h3 className="text-sm font-semibold text-gray-900">Variant availability</h3>
        <dl className="mt-2 grid gap-2 text-sm sm:grid-cols-2">
          <div><dt className="text-jp-muted">Desktop</dt><dd>{asset.desktop.placeholderLabel}</dd></div>
          <div><dt className="text-jp-muted">Mobile</dt><dd>{asset.mobile.placeholderLabel}</dd></div>
          <div><dt className="text-jp-muted">Day</dt><dd>{asset.dayVariant?.placeholderLabel ?? "Not available"}</dd></div>
          <div><dt className="text-jp-muted">Night</dt><dd>{asset.nightVariant?.placeholderLabel ?? "Not available"}</dd></div>
          <div><dt className="text-jp-muted">Alt text</dt><dd>{asset.altText.trim() ? asset.altText : <span className="text-red-700">Missing</span>}</dd></div>
          <div><dt className="text-jp-muted">Focal point</dt><dd>{asset.focalPointX}, {asset.focalPointY}</dd></div>
          <div><dt className="text-jp-muted">Safe area</dt><dd>{asset.safeArea}</dd></div>
        </dl>
      </section>

      <section>
        <h3 className="text-sm font-semibold text-gray-900">Usage references</h3>
        {usages.length === 0 ? (
          <p className="mt-1 text-sm text-jp-muted">No referencing records.</p>
        ) : (
          <ul className="mt-2 space-y-1 text-sm">
            {usages.map((u) => (
              <li key={`${u.type}-${u.id}`}>{u.type}: {u.id}</li>
            ))}
          </ul>
        )}
      </section>

      <CmsValidationSummary issues={validation.issues} />
      <CmsRevisionTimeline revisions={mockCmsRevisions} entityId={asset.id} />

      <section>
        <CmsPreviewModeSelector mode={previewMode} onChange={onPreviewModeChange} />
        <div className="mt-3">
          <CmsPreviewShell mode={previewMode} label={asset.internalName}>
            <AssetPlaceholderPreview asset={asset} mode={previewMode} />
          </CmsPreviewShell>
        </div>
      </section>
    </div>
  );
}

function AssetPlaceholderPreview({ asset, mode }: { asset: CmsAsset; mode: CmsPreviewMode }) {
  const isNight = mode.includes("night");
  const isMobile = mode.includes("mobile");
  const variant = isMobile ? asset.mobile : asset.desktop;
  const label = isNight && asset.nightVariant ? asset.nightVariant.placeholderLabel : isNight && !asset.nightVariant ? "Night variant missing" : variant.placeholderLabel;

  return (
    <div
      className={`flex aspect-video items-center justify-center rounded-xl border-2 border-dashed ${isNight ? "border-gray-600 bg-gray-800 text-gray-200" : "border-emerald-300 bg-emerald-50 text-emerald-900"}`}
      role="img"
      aria-label={asset.altText.trim() || "Alt text missing"}
    >
      <div className="text-center text-sm">
        <p className="font-medium">{label}</p>
        <p className="mt-1 text-xs opacity-70">{variant.width}×{variant.height}</p>
        {!asset.altText.trim() ? <p className="mt-2 text-xs text-red-600">Missing alt text</p> : null}
      </div>
    </div>
  );
}
