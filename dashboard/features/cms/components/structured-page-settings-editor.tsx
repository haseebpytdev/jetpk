"use client";

import { useMemo, useState } from "react";
import { CmsMediaPickerDialog } from "@/features/cms/components/cms-media-picker-dialog";
import { CmsHtmlBlockBuilder } from "@/features/cms/components/cms-html-block-builder";
import { attachPageSettingsAsset, uploadPageSettingsAsset } from "@/services/operational-api";

type SectionDef = { key: string; label: string; fields: string[] };

type Props = {
  pageKey: string;
  content: Record<string, unknown>;
  sections: SectionDef[];
  onChange: (next: Record<string, unknown>) => void;
  onMediaAttached?: () => void;
  disabled?: boolean;
};

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

export function StructuredPageSettingsEditor({ pageKey, content, sections, onChange, onMediaAttached, disabled }: Props) {
  const [pickerSection, setPickerSection] = useState<string | null>(null);
  const seo = asObject(content.seo);

  const mediaSectionKeys = useMemo(() => new Set(["hero", "support_cta", "seo"]), []);

  function patchSection(sectionKey: string, patch: Record<string, unknown>) {
    onChange({
      ...content,
      [sectionKey]: {
        ...asObject(content[sectionKey]),
        ...patch,
      },
    });
  }

  return (
    <div className="space-y-4" data-testid="cms-page-editor">
      <div className="rounded-lg border border-jp-border p-3">
        <h3 className="text-sm font-semibold">Page information</h3>
        <p className="mt-1 text-xs text-jp-muted">Key: {pageKey}</p>
      </div>

      <div className="rounded-lg border border-jp-border p-3">
        <h3 className="text-sm font-semibold">SEO</h3>
        <div className="mt-2 grid gap-2 sm:grid-cols-2">
          <FieldInput label="SEO title" value={String(seo.title ?? "")} onChange={(v) => onChange({ ...content, seo: { ...seo, title: v } })} />
          <FieldInput label="Meta description" value={String(seo.description ?? "")} multiline onChange={(v) => onChange({ ...content, seo: { ...seo, description: v } })} />
          <FieldInput label="Canonical" value={String(seo.canonical ?? "")} onChange={(v) => onChange({ ...content, seo: { ...seo, canonical: v } })} />
          <FieldInput label="Robots" value={String(seo.robots ?? "")} onChange={(v) => onChange({ ...content, seo: { ...seo, robots: v } })} />
        </div>
      </div>

      {sections.filter((s) => s.key !== "seo").map((section) => {
        const sectionData = asObject(content[section.key]);
        const hasItems = section.fields.includes("items") || section.fields.includes("cards") || section.fields.includes("sections");
        const listKey = section.fields.includes("cards")
          ? "cards"
          : section.fields.includes("sections")
            ? "sections"
            : "items";
        const scalarFields = section.fields.filter((f) => !["items", "cards", "sections"].includes(f));

        return (
          <fieldset key={section.key} className="space-y-2 rounded-lg border border-jp-border p-3" disabled={disabled}>
            <legend className="text-sm font-medium">{section.label}</legend>
            <div className="grid gap-2 sm:grid-cols-2">
              {scalarFields.map((field) => (
                <FieldInput
                  key={field}
                  label={field.replaceAll("_", " ")}
                  value={String(sectionData[field] ?? "")}
                  multiline={TEXTAREA_FIELDS.has(field)}
                  onChange={(v) => patchSection(section.key, { [field]: v })}
                />
              ))}
            </div>

            {mediaSectionKeys.has(section.key) ? (
              <div className="mt-2">
                <button type="button" className="rounded-lg border border-jp-border px-3 py-2 text-xs" onClick={() => setPickerSection(section.key)} data-testid="cms-media-picker-trigger">
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
                onChange={(items) => patchSection(section.key, { [listKey]: items })}
              />
            ) : null}
          </fieldset>
        );
      })}

      {pageKey.startsWith("custom:") ? (
        <div className="rounded-lg border border-jp-border p-3">
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
