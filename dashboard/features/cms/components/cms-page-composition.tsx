"use client";

import { useState } from "react";
import { getSectionDefinition } from "@/features/cms/registry/section-registry";
import { CmsStatusBadge } from "@/components/ui/status-badge";
import { Card, CardTitle } from "@/components/ui/card";
import { mockCmsSections } from "@/mocks/cms-fixtures";
import { resolveValidation } from "@/lib/cms/query-filters";
import type { CmsPage } from "@/types/cms";

type Props = {
  page: CmsPage;
  activeSectionId?: string | null;
  onActiveSectionChange?: (sectionId: string) => void;
};

export function CmsPageComposition({ page, activeSectionId, onActiveSectionChange }: Props) {
  const sections = page.sectionIds
    .map((id) => mockCmsSections.find((s) => s.id === id))
    .filter(Boolean)
    .sort((a, b) => (a?.sortOrder ?? 0) - (b?.sortOrder ?? 0));

  const [localOrder, setLocalOrder] = useState<string[] | null>(null);
  const orderedIds = localOrder ?? sections.map((s) => s!.id);
  const orderedSections = orderedIds
    .map((id) => sections.find((s) => s?.id === id))
    .filter(Boolean);

  const currentActiveId = activeSectionId ?? orderedSections[0]?.id ?? null;

  const move = (index: number, direction: -1 | 1) => {
    const next = [...orderedIds];
    const target = index + direction;
    if (target < 0 || target >= next.length) return;
    [next[index], next[target]] = [next[target], next[index]];
    setLocalOrder(next);
  };

  return (
    <Card className="p-4" data-testid="cms-page-composition">
      <CardTitle className="text-base">Page composition</CardTitle>
      {localOrder ? (
        <p role="status" className="mt-2 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-900">
          Unsaved preview — section order is local only and will not persist.
        </p>
      ) : null}
      <p className="mt-2 font-medium text-gray-900">{page.title}</p>

      <nav className="mt-3 flex flex-wrap gap-2" aria-label="Page sections" data-testid="cms-section-nav">
        {orderedSections.map((section) => {
          if (!section) return null;
          const def = getSectionDefinition(section.sectionType);
          const isActive = section.id === currentActiveId;
          return (
            <button
              key={section.id}
              type="button"
              aria-current={isActive ? "true" : undefined}
              className={`rounded-full border px-3 py-1 text-xs font-medium ${
                isActive ? "border-jp-accent bg-jp-accent/10 text-jp-text" : "border-jp-border bg-white text-jp-muted"
              }`}
              onClick={() => onActiveSectionChange?.(section.id)}
            >
              {def?.label ?? section.sectionType}
            </button>
          );
        })}
      </nav>

      <ul className="mt-3 space-y-2 font-mono text-sm" aria-label="Ordered sections">
        {orderedSections.map((section, index) => {
          if (!section) return null;
          const def = getSectionDefinition(section.sectionType);
          const validation = resolveValidation(section, "section");
          const isActive = section.id === currentActiveId;
          return (
            <li
              key={section.id}
              className={`rounded-lg border px-3 py-2 ${isActive ? "border-jp-accent bg-jp-accent/5" : "border-jp-border"}`}
              data-section-id={section.id}
            >
              <div className="flex flex-wrap items-start justify-between gap-2">
                <div className="min-w-0">
                  <span className="text-jp-muted">{index + 1}.</span>{" "}
                  <span className="font-sans font-medium">{def?.label ?? section.sectionType}</span>
                  <p className="mt-1 break-all font-mono text-xs text-jp-muted">{def?.frontendComponentKey}</p>
                </div>
                <div className="flex flex-wrap gap-1">
                  <CmsStatusBadge status={section.variant} label={section.variant} />
                  <CmsStatusBadge status={validation.valid ? "valid" : "warning"} label={validation.valid ? "Valid" : "Review"} />
                </div>
              </div>
              <dl className="mt-2 grid gap-1 text-xs text-jp-muted sm:grid-cols-2">
                <div>Theme: {section.themeMode}</div>
                <div>Device: {section.deviceVisibility}</div>
                <div>Assets: {section.assetIds.length}</div>
                <div>Order: {section.sortOrder}</div>
              </dl>
              <div className="mt-2 flex gap-2">
                <button
                  type="button"
                  className="min-h-9 rounded-lg border border-jp-border px-2 text-xs hover:bg-gray-50 disabled:opacity-40"
                  disabled={index === 0}
                  onClick={() => move(index, -1)}
                  aria-label={`Move ${def?.label} up`}
                >
                  ↑ Up
                </button>
                <button
                  type="button"
                  className="min-h-9 rounded-lg border border-jp-border px-2 text-xs hover:bg-gray-50 disabled:opacity-40"
                  disabled={index === orderedSections.length - 1}
                  onClick={() => move(index, 1)}
                  aria-label={`Move ${def?.label} down`}
                >
                  ↓ Down
                </button>
              </div>
            </li>
          );
        })}
      </ul>
      {localOrder ? (
        <button
          type="button"
          className="mt-3 min-h-11 rounded-xl border border-jp-border px-4 text-sm font-medium hover:bg-gray-50"
          onClick={() => setLocalOrder(null)}
        >
          Reset preview order
        </button>
      ) : null}
    </Card>
  );
}
