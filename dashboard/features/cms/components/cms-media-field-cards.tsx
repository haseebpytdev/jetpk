"use client";

import { mockCmsAssets } from "@/mocks/cms-fixtures";

type Props = {
  assetIds: string[];
};

export function CmsMediaFieldCards({ assetIds }: Props) {
  const assets = assetIds
    .map((id) => mockCmsAssets.find((asset) => asset.id === id))
    .filter(Boolean);

  if (!assets.length) {
    return null;
  }

  return (
    <section data-testid="cms-media-field-cards">
      <h3 className="text-sm font-semibold text-gray-900">Media fields</h3>
      <p className="mt-1 text-xs text-jp-muted">
        Read-only preview. Upload and approval remain in Laravel Page Settings.
      </p>
      <ul className="mt-3 grid gap-3 sm:grid-cols-2">
        {assets.map((asset) => (
          <li key={asset!.id} className="rounded-xl border border-jp-border bg-white p-3">
            <div
              className="flex aspect-[4/3] items-center justify-center rounded-lg border border-dashed border-jp-border bg-jp-page text-xs text-jp-muted"
              data-testid="cms-media-card-preview"
            >
              {asset!.internalName}
            </div>
            <p className="mt-2 truncate text-sm font-medium text-gray-900">{asset!.internalName}</p>
            <p className="truncate text-xs text-jp-muted">{asset!.altText || "No alt text"}</p>
            <div className="jp-file-control mt-3 flex items-center gap-2" data-testid="cms-file-control">
              <button type="button" className="rounded-lg border border-jp-border px-2 py-1 text-xs" disabled>
                Choose image
              </button>
              <span className="truncate text-xs text-jp-muted">{asset!.internalName}</span>
            </div>
          </li>
        ))}
      </ul>
    </section>
  );
}
