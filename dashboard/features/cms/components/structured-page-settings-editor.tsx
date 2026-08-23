"use client";

import { useEffect, useMemo, useState } from "react";
import { CmsMediaPickerDialog } from "@/features/cms/components/cms-media-picker-dialog";
import { CmsHtmlBlockBuilder } from "@/features/cms/components/cms-html-block-builder";
import { attachPageSettingsAsset, uploadPageSettingsAsset } from "@/services/operational-api";

type SectionDef = { key: string; label: string; fields: string[] };

type NavKey = "page" | "seo" | "hero" | "sections" | "media" | "publishing";

type PublishingInfo = {
  has_draft?: boolean;
  has_published?: boolean;
  archived?: boolean;
  can_unpublish?: boolean;
  status?: string;
};

type Props = {
  pageKey: string;
  content: Record<string, unknown>;
  sections: SectionDef[];
  onChange: (next: Record<string, unknown>) => void;
  onMediaAttached?: () => void;
  disabled?: boolean;
  previewUrl?: string | null;
  publishing?: PublishingInfo | null;
};

const NAV_ITEMS: Array<{ id: NavKey; label: string }> = [
  { id: "page", label: "Page" },
  { id: "seo", label: "SEO" },
  { id: "hero", label: "Hero" },
  { id: "sections", label: "Sections" },
  { id: "media", label: "Media" },
  { id: "publishing", label: "Publishing" },
];

const TEXTAREA_FIELDS = new Set([
  "body",
  "description",
  "subtitle",
  "intro",
  "helper_text",
  "success_copy",
  "text",
  "how_it_works",
  "hint",
]);

function asObject(value: unknown): Record<string, unknown> {
  return value && typeof value === "object" && !Array.isArray(value) ? (value as Record<string, unknown>) : {};
}

function asArray(value: unknown): Array<Record<string, unknown>> {
  return Array.isArray(value) ? (value as Array<Record<string, unknown>>) : [];
}

function FieldInput({
  label,
  value,
  onChange,
  multiline,
}: {
  label: string;
  value: string;
  onChange: (v: string) => void;
  multiline?: boolean;
}) {
  return (
    <label className="block text-xs">
      {label}
      {multiline ? (
        <textarea className="mt-1 w-full rounded-lg border border-jp-border p-2 text-sm" rows={3} value={value} onChange={(e) => onChange(e.target.value)} />
      ) : (
        <input className="mt-1 w-full rounded-lg border border-jp-border px-2 py-1.5 text-sm" value={value} onChange={(e) => onChange(e.target.value)} />
      )}
    </label>
  );
}

function ItemRepeater({
  items,
  fields,
  onChange,
  addLabel,
}: {
  items: Array<Record<string, unknown>>;
  fields: string[];
  onChange: (items: Array<Record<string, unknown>>) => void;
  addLabel: string;
}) {
  return (
    <div className="space-y-2" data-testid="cms-page-blocks">
      {items.map((item, index) => (
        <div key={index} className="rounded-lg border border-jp-border p-2">
          <div className="grid gap-2 sm:grid-cols-2">
            {fields.map((field) => (
              <FieldInput
                key={field}
                label={field.replaceAll("_", " ")}
                value={String(item[field] ?? "")}
                multiline={TEXTAREA_FIELDS.has(field)}
                onChange={(v) => {
                  const next = [...items];
                  next[index] = { ...item, [field]: v };
                  onChange(next);
                }}
              />
            ))}
          </div>
          <div className="mt-2 flex flex-wrap gap-2">
            <button type="button" className="rounded border border-jp-border px-2 py-1 text-xs" onClick={() => {
              const next = [...items];
              if (index > 0) {
                const tmp = next[index - 1];
                next[index - 1] = next[index];
                next[index] = tmp;
                onChange(next);
              }
            }}>Move up</button>
            <button type="button" className="rounded border border-jp-border px-2 py-1 text-xs" onClick={() => {
              const next = [...items];
              if (index < next.length - 1) {
                const tmp = next[index + 1];
                next[index + 1] = next[index];
                next[index] = tmp;
                onChange(next);
              }
            }}>Move down</button>
            <button type="button" className="rounded border border-jp-border px-2 py-1 text-xs" onClick={() => {
              const next = [...items];
              next.splice(index + 1, 0, { ...item });
              onChange(next);
            }}>Duplicate</button>
            <button type="button" className="rounded border border-red-200 px-2 py-1 text-xs text-red-700" onClick={() => onChange(items.filter((_, i) => i !== index))}>Delete</button>
          </div>
        </div>
      ))}
      <button
        type="button"
        className="rounded-lg border border-jp-border px-3 py-2 text-xs font-medium"
        onClick={() => {
          const blank: Record<string, unknown> = {};
          fields.forEach((f) => {
            blank[f] = "";
          });
          onChange([...items, blank]);
        }}
      >
        + {addLabel}
      </button>
    </div>
  );
}

function SectionFields({
  section,
  sectionData,
  disabled,
  mediaSectionKeys,
  onPickMedia,
  onPatch,
}: {
  section: SectionDef;
  sectionData: Record<string, unknown>;
  disabled?: boolean;
  mediaSectionKeys: Set<string>;
  onPickMedia: (sectionKey: string) => void;
  onPatch: (sectionKey: string, patch: Record<string, unknown>) => void;
}) {
  const hasItems = section.fields.includes("items") || section.fields.includes("cards") || section.fields.includes("sections");
  const listKey = section.fields.includes("cards")
    ? "cards"
    : section.fields.includes("sections")
      ? "sections"
      : "items";
  const scalarFields = section.fields.filter((f) => !["items", "cards", "sections"].includes(f));

  return (
    <fieldset className="space-y-2" disabled={disabled}>
      <legend className="text-sm font-medium">{section.label}</legend>
      <div className="grid gap-2 sm:grid-cols-2">
        {scalarFields.map((field) => (
          <FieldInput
            key={field}
            label={field.replaceAll("_", " ")}
            value={String(sectionData[field] ?? "")}
            multiline={TEXTAREA_FIELDS.has(field)}
            onChange={(v) => onPatch(section.key, { [field]: v })}
          />
        ))}
      </div>

      {mediaSectionKeys.has(section.key) ? (
        <div className="mt-2">
          <button type="button" className="rounded-lg border border-jp-border px-3 py-2 text-xs" onClick={() => onPickMedia(section.key)} data-testid="cms-media-picker-trigger">
            Select media for {section.label}
          </button>
        </div>
      ) : null}

      {hasItems ? (
        <ItemRepeater
          items={asArray(sectionData[listKey])}
          fields={
            section.key === "faq" || section.key === "categories" || listKey === "sections"
              ? ["question", "answer", "title", "body"]
              : ["title", "body", "label", "url", "description"]
          }
          addLabel={`Add ${section.label.toLowerCase()} item`}
          onChange={(items) => onPatch(section.key, { [listKey]: items })}
        />
      ) : null}
    </fieldset>
  );
}

export function StructuredPageSettingsEditor({
  pageKey,
  content,
  sections,
  onChange,
  onMediaAttached,
  disabled,
  previewUrl = null,
  publishing = null,
}: Props) {
  const [pickerSection, setPickerSection] = useState<string | null>(null);
  const [activeNav, setActiveNav] = useState<NavKey>("page");
  const [mobilePane, setMobilePane] = useState<"editor" | "preview">("editor");
  const seo = asObject(content.seo);

  const mediaSectionKeys = useMemo(() => new Set(["hero", "support_cta", "seo"]), []);

  const heroSection = useMemo(() => sections.find((s) => s.key === "hero") ?? null, [sections]);
  const contentSections = useMemo(
    () => sections.filter((s) => s.key !== "seo" && s.key !== "hero"),
    [sections],
  );
  const [activeContentSection, setActiveContentSection] = useState<string | null>(null);

  useEffect(() => {
    setActiveContentSection((prev) => {
      if (prev && contentSections.some((s) => s.key === prev)) return prev;
      return contentSections[0]?.key ?? null;
    });
  }, [contentSections]);

  function patchSection(sectionKey: string, patch: Record<string, unknown>) {
    onChange({
      ...content,
      [sectionKey]: {
        ...asObject(content[sectionKey]),
        ...patch,
      },
    });
  }

  const selectedContentSection = contentSections.find((s) => s.key === activeContentSection) ?? contentSections[0] ?? null;

  const previewPane = (
    <aside className="h-fit space-y-2 lg:sticky lg:top-4" data-testid="cms-page-preview-panel">
      <h3 className="text-sm font-semibold">Live preview</h3>
      <p className="text-xs text-jp-muted">Public page URL. Draft changes appear after Preview / Publish.</p>
      {previewUrl ? (
        <iframe
          title={`Preview ${pageKey}`}
          src={previewUrl}
          className="h-[min(70vh,36rem)] w-full rounded-lg border border-jp-border bg-white"
        />
      ) : (
        <div className="flex h-48 items-center justify-center rounded-lg border border-dashed border-jp-border bg-jp-surface/40 px-3 text-center text-xs text-jp-muted">
          Preview URL unavailable for this page.
        </div>
      )}
    </aside>
  );

  const editorPane = (
    <div className="min-w-0 space-y-3">
      {activeNav === "page" ? (
        <div className="rounded-lg border border-jp-border p-3">
          <h3 className="text-sm font-semibold">Page information</h3>
          <p className="mt-1 text-xs text-jp-muted">Key: {pageKey}</p>
        </div>
      ) : null}

      {activeNav === "seo" ? (
        <div className="rounded-lg border border-jp-border p-3">
          <h3 className="text-sm font-semibold">SEO</h3>
          <div className="mt-2 grid gap-2 sm:grid-cols-2">
            <FieldInput label="SEO title" value={String(seo.title ?? "")} onChange={(v) => onChange({ ...content, seo: { ...seo, title: v } })} />
            <FieldInput label="Meta description" value={String(seo.description ?? "")} multiline onChange={(v) => onChange({ ...content, seo: { ...seo, description: v } })} />
            <FieldInput label="Canonical" value={String(seo.canonical ?? "")} onChange={(v) => onChange({ ...content, seo: { ...seo, canonical: v } })} />
            <FieldInput label="Robots" value={String(seo.robots ?? "")} onChange={(v) => onChange({ ...content, seo: { ...seo, robots: v } })} />
          </div>
          {mediaSectionKeys.has("seo") ? (
            <div className="mt-2">
              <button type="button" className="rounded-lg border border-jp-border px-3 py-2 text-xs" onClick={() => setPickerSection("seo")} data-testid="cms-media-picker-trigger">
                Select media for SEO
              </button>
            </div>
          ) : null}
        </div>
      ) : null}

      {activeNav === "hero" ? (
        <div className="rounded-lg border border-jp-border p-3">
          {heroSection ? (
            <SectionFields
              section={heroSection}
              sectionData={asObject(content.hero)}
              disabled={disabled}
              mediaSectionKeys={mediaSectionKeys}
              onPickMedia={setPickerSection}
              onPatch={patchSection}
            />
          ) : (
            <p className="text-xs text-jp-muted">This page has no hero section in its schema.</p>
          )}
        </div>
      ) : null}

      {activeNav === "sections" ? (
        <div className="space-y-3 rounded-lg border border-jp-border p-3">
          {contentSections.length === 0 && !pageKey.startsWith("custom:") ? (
            <p className="text-xs text-jp-muted">No additional content sections for this page.</p>
          ) : null}
          {contentSections.length > 0 ? (
            <div className="flex flex-wrap gap-2">
              {contentSections.map((section) => (
                <button
                  key={section.key}
                  type="button"
                  className={`rounded-full border px-3 py-1 text-xs font-medium ${
                    section.key === selectedContentSection?.key
                      ? "border-jp-accent bg-jp-accent/10 text-jp-text"
                      : "border-jp-border bg-white text-jp-muted"
                  }`}
                  aria-current={section.key === selectedContentSection?.key ? "true" : undefined}
                  onClick={() => setActiveContentSection(section.key)}
                >
                  {section.label}
                </button>
              ))}
            </div>
          ) : null}
          {selectedContentSection ? (
            <SectionFields
              section={selectedContentSection}
              sectionData={asObject(content[selectedContentSection.key])}
              disabled={disabled}
              mediaSectionKeys={mediaSectionKeys}
              onPickMedia={setPickerSection}
              onPatch={patchSection}
            />
          ) : null}
          {pageKey.startsWith("custom:") ? (
            <div className="border-t border-jp-border pt-3">
              <h3 className="text-sm font-semibold">Structured blocks</h3>
              <p className="mt-1 text-xs text-jp-muted">Typed block builder — no raw JSON required.</p>
              <div className="mt-3">
                <CmsHtmlBlockBuilder
                  content={String(asObject(content.sections).html ?? "")}
                  onChange={(html) => onChange({ ...content, sections: { ...asObject(content.sections), html } })}
                  disabled={disabled}
                />
              </div>
            </div>
          ) : null}
        </div>
      ) : null}

      {activeNav === "media" ? (
        <div className="space-y-3 rounded-lg border border-jp-border p-3">
          <h3 className="text-sm font-semibold">Media</h3>
          <p className="text-xs text-jp-muted">Attach images from the media library to supported page areas.</p>
          <div className="flex flex-wrap gap-2">
            {Array.from(mediaSectionKeys)
              .filter((key) => key === "seo" || sections.some((s) => s.key === key) || key === "hero")
              .map((key) => {
                const label = key === "seo" ? "SEO" : sections.find((s) => s.key === key)?.label ?? key;
                return (
                  <button
                    key={key}
                    type="button"
                    className="rounded-lg border border-jp-border px-3 py-2 text-xs"
                    onClick={() => setPickerSection(key)}
                    data-testid="cms-media-picker-trigger"
                  >
                    Select media for {label}
                  </button>
                );
              })}
          </div>
        </div>
      ) : null}

      {activeNav === "publishing" ? (
        <div className="rounded-lg border border-jp-border p-3">
          <h3 className="text-sm font-semibold">Publishing</h3>
          <dl className="mt-2 grid gap-2 text-sm sm:grid-cols-2">
            <div>
              <dt className="text-xs text-jp-muted">Status</dt>
              <dd>{publishing?.status ?? (publishing?.has_published ? "published" : publishing?.has_draft ? "draft" : "—")}</dd>
            </div>
            <div>
              <dt className="text-xs text-jp-muted">Draft</dt>
              <dd>{publishing?.has_draft ? "Yes" : "No"}</dd>
            </div>
            <div>
              <dt className="text-xs text-jp-muted">Published</dt>
              <dd>{publishing?.has_published ? "Yes" : "No"}</dd>
            </div>
            <div>
              <dt className="text-xs text-jp-muted">Archived</dt>
              <dd>{publishing?.archived ? "Yes" : "No"}</dd>
            </div>
          </dl>
          <p className="mt-3 text-xs text-jp-muted">
            Use Save draft, Preview, Publish, and Archive in the toolbar above. Public production content is unchanged until Publish.
          </p>
        </div>
      ) : null}
    </div>
  );

  return (
    <div className="space-y-3" data-testid="cms-page-editor-split">
      <div className="flex gap-2 lg:hidden" role="tablist" aria-label="Editor or preview">
        <button
          type="button"
          role="tab"
          aria-selected={mobilePane === "editor"}
          className={`min-h-11 flex-1 rounded-xl border px-3 text-sm ${
            mobilePane === "editor" ? "border-jp-accent bg-jp-accent/10 font-medium" : "border-jp-border"
          }`}
          onClick={() => setMobilePane("editor")}
        >
          Editor
        </button>
        <button
          type="button"
          role="tab"
          aria-selected={mobilePane === "preview"}
          className={`min-h-11 flex-1 rounded-xl border px-3 text-sm ${
            mobilePane === "preview" ? "border-jp-accent bg-jp-accent/10 font-medium" : "border-jp-border"
          }`}
          onClick={() => setMobilePane("preview")}
        >
          Preview
        </button>
      </div>

      <div
        className={`gap-4 ${
          previewUrl
            ? "lg:grid lg:grid-cols-[minmax(10rem,14rem)_minmax(0,1fr)_minmax(16rem,45%)]"
            : "lg:grid lg:grid-cols-[minmax(10rem,14rem)_minmax(0,1fr)]"
        }`}
      >
        <nav
          className={`space-y-1 ${mobilePane === "preview" ? "hidden lg:block" : "block"}`}
          aria-label="Page section navigation"
          data-testid="cms-page-section-nav"
        >
          {NAV_ITEMS.map((item) => {
            const expanded = activeNav === item.id;
            return (
              <button
                key={item.id}
                type="button"
                aria-expanded={expanded}
                aria-current={expanded ? "true" : undefined}
                className={`flex w-full items-center justify-between rounded-lg border px-3 py-2 text-left text-sm ${
                  expanded ? "border-jp-accent bg-jp-accent/10 font-medium" : "border-jp-border bg-white text-jp-muted"
                }`}
                onClick={() => {
                  setActiveNav(item.id);
                  setMobilePane("editor");
                }}
              >
                <span>{item.label}</span>
                <span className="text-xs opacity-60" aria-hidden>{expanded ? "▾" : "▸"}</span>
              </button>
            );
          })}
        </nav>

        <div className={mobilePane === "preview" ? "hidden lg:block" : "block"}>{editorPane}</div>

        <div className={mobilePane === "editor" ? "hidden lg:block" : "block"}>{previewPane}</div>
      </div>

      <CmsMediaPickerDialog
        open={pickerSection !== null}
        title="Select from Media Library"
        onClose={() => setPickerSection(null)}
        onUploadFile={async (file) => {
          if (!pickerSection) return;
          const formData = new FormData();
          formData.set("asset_key", pickerSection === "hero" ? "hero_background" : `${pickerSection}_image`);
          formData.set("file", file);
          formData.set("alt_text", file.name);
          const result = await uploadPageSettingsAsset(pageKey, formData);
          if (!result.ok) throw new Error(result.message ?? "Upload failed");
        }}
        onSelect={(item) => {
          if (!pickerSection) return;
          const assetKey = pickerSection === "hero" ? "hero_background" : `${pickerSection}_image`;
          void attachPageSettingsAsset(pageKey, {
            asset_key: assetKey,
            agency_media_id: item.id,
            alt_text: item.alt_text,
          }).then((result) => {
            if (!result.ok) return;
            setPickerSection(null);
            onMediaAttached?.();
          });
        }}
      />
    </div>
  );
}
