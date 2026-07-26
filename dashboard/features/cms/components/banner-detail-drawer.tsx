"use client";

import { CmsPreviewModeSelector } from "@/features/cms/components/cms-preview-mode-selector";
import { CmsPreviewShell } from "@/features/cms/components/cms-preview-shell";
import { CmsRevisionTimeline } from "@/features/cms/components/cms-revision-timeline";
import { CmsValidationSummary } from "@/features/cms/components/cms-validation-summary";
import { CMS_BANNER_FAMILY_RULES } from "@/features/cms/registry/section-registry";
import { CmsStatusBadge } from "@/components/ui/status-badge";
import { mockCmsAssets, mockCmsRevisions } from "@/mocks/cms-fixtures";
import { validateBanner } from "@/features/cms/validation/cms-validation";
import { mergeValidationIssues } from "@/features/cms/validation/link-validation";
import type { CmsBanner, CmsPreviewMode } from "@/types/cms";

export function BannerDetailDrawerContent({
  banner,
  previewMode,
  onPreviewModeChange,
}: {
  banner: CmsBanner;
  previewMode: CmsPreviewMode;
  onPreviewModeChange: (mode: CmsPreviewMode) => void;
}) {
  const rules = CMS_BANNER_FAMILY_RULES[banner.family];
  const validation = mergeValidationIssues(validateBanner(banner));
  const desktopAsset = mockCmsAssets.find((a) => a.id === banner.desktopAssetId);
  const mobileAsset = mockCmsAssets.find((a) => a.id === banner.mobileAssetId);

  return (
    <div className="space-y-4" data-testid="cms-banner-drawer">
      <section>
        <h3 className="text-sm font-semibold text-gray-900">Banner identity</h3>
        <dl className="mt-2 grid gap-2 text-sm sm:grid-cols-2">
          <div><dt className="text-jp-muted">Banner ID</dt><dd>{banner.id}</dd></div>
          <div><dt className="text-jp-muted">Family</dt><dd>{banner.family}</dd></div>
          <div><dt className="text-jp-muted">Placements</dt><dd>{banner.placements.join(", ")}</dd></div>
          <div><dt className="text-jp-muted">Status</dt><dd><CmsStatusBadge status={banner.status} /></dd></div>
        </dl>
      </section>

      <section>
        <h3 className="text-sm font-semibold text-gray-900">Family constraints</h3>
        <dl className="mt-2 grid gap-2 text-sm sm:grid-cols-2">
          <div><dt className="text-jp-muted">Desktop ratio</dt><dd>{banner.desktopAspectRatio}</dd></div>
          <div><dt className="text-jp-muted">Mobile ratio</dt><dd>{banner.mobileAspectRatio}</dd></div>
          <div><dt className="text-jp-muted">Day/night</dt><dd>{banner.supportsDayNight ? "Supported" : "Not required"}</dd></div>
          <div><dt className="text-jp-muted">Overlay</dt><dd>{rules?.overlayAllowed ? "Allowed" : "Not allowed"}</dd></div>
          <div><dt className="text-jp-muted">Text</dt><dd>{rules?.textAllowed ? "Allowed" : "Not allowed"}</dd></div>
          <div><dt className="text-jp-muted">CTA</dt><dd>{rules?.ctaAllowed ? "Allowed" : "Not allowed"}</dd></div>
        </dl>
      </section>

      <section>
        <h3 className="text-sm font-semibold text-gray-900">Assets</h3>
        <dl className="mt-2 space-y-2 text-sm">
          <div><dt className="text-jp-muted">Desktop asset</dt><dd>{desktopAsset?.internalName ?? "—"}</dd></div>
          <div><dt className="text-jp-muted">Mobile asset</dt><dd>{mobileAsset?.internalName ?? "—"}</dd></div>
          <div><dt className="text-jp-muted">Alt text</dt><dd>{banner.altText || <span className="text-red-700">Missing</span>}</dd></div>
          <div><dt className="text-jp-muted">Focal point</dt><dd>{banner.focalPointX}, {banner.focalPointY}</dd></div>
        </dl>
      </section>

      <CmsValidationSummary issues={validation.issues} />
      <CmsRevisionTimeline revisions={mockCmsRevisions} entityId={banner.id} />

      <section>
        <CmsPreviewModeSelector mode={previewMode} onChange={onPreviewModeChange} />
        <div className="mt-3">
          <CmsPreviewShell mode={previewMode} label={banner.title}>
            <BannerFamilyPreview banner={banner} />
          </CmsPreviewShell>
        </div>
      </section>
    </div>
  );
}

function BannerFamilyPreview({ banner }: { banner: CmsBanner }) {
  const aspect = banner.family === "support" ? "aspect-[21/9]" : banner.family === "notice" ? "py-3" : "aspect-video";
  return (
    <div className={`relative overflow-hidden rounded-xl bg-gradient-to-br from-emerald-100 to-emerald-200 ${aspect}`} data-banner-family={banner.family}>
      <div className="absolute inset-0 flex flex-col justify-end p-4">
        <p className="text-lg font-semibold">{banner.title}</p>
        {banner.subtitle ? <p className="text-sm opacity-80">{banner.subtitle}</p> : null}
        {banner.cta ? <span className="mt-2 inline-block w-fit rounded-lg bg-emerald-700 px-3 py-1 text-sm text-white">{banner.cta.label}</span> : null}
        {banner.family === "hero" ? (
          <div className="mt-3 rounded-lg border border-dashed border-emerald-400 bg-white/60 p-2 text-xs">Flight search placeholder (non-interactive)</div>
        ) : null}
      </div>
    </div>
  );
}
