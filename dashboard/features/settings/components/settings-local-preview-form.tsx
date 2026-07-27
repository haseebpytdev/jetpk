"use client";

import { useEffect, useState } from "react";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/page-layout";
import { Select } from "@/components/ui/select";

export type SettingsPreviewField = {
  key: string;
  label: string;
  description?: string;
  type: "text" | "email" | "tel" | "number" | "select" | "textarea" | "boolean";
  options?: { value: string; label: string }[];
  maxLength?: number;
  min?: number;
  max?: number;
};

type Props = {
  fields: SettingsPreviewField[];
  baselineValues: Record<string, unknown>;
  onApply: (values: Record<string, unknown>) => void;
  onReset: () => void;
  dirty: boolean;
  title?: string;
};

function readValue(values: Record<string, unknown>, key: string): string | boolean | number {
  const value = values[key];
  if (typeof value === "boolean" || typeof value === "number") return value;
  if (value === null || value === undefined) return "";
  return String(value);
}

export function SettingsLocalPreviewForm({
  fields,
  baselineValues,
  onApply,
  onReset,
  dirty,
  title = "Local preview editing",
}: Props) {
  const [draft, setDraft] = useState<Record<string, unknown>>(baselineValues);

  useEffect(() => {
    setDraft(baselineValues);
  }, [baselineValues]);

  const applyPreview = () => {
    onApply({ ...draft });
  };

  const handleReset = () => {
    setDraft(baselineValues);
    onReset();
  };

  const updateField = (key: string, value: string | boolean | number) => {
    setDraft((prev) => ({ ...prev, [key]: value }));
  };

  return (
    <section className="rounded-xl border border-jp-border p-4" data-testid="settings-local-preview-form">
      <h3 className="text-sm font-semibold text-gray-900">{title}</h3>
      {dirty ? (
        <p role="status" className="mt-2 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-900">
          Unsaved preview — changes are local to this session only.
        </p>
      ) : null}

      <div className="mt-3 grid gap-3 sm:grid-cols-2">
        {fields.map((field) => {
          const id = `settings-preview-${field.key}`;
          const value = readValue(draft, field.key);

          if (field.type === "boolean") {
            return (
              <div key={field.key} className="flex items-start gap-2 sm:col-span-2">
                <input
                  id={id}
                  type="checkbox"
                  className="mt-1 h-4 w-4 rounded border-jp-border"
                  checked={value === true}
                  onChange={(e) => updateField(field.key, e.target.checked)}
                />
                <div>
                  <Label htmlFor={id}>{field.label}</Label>
                  {field.description ? <p className="text-xs text-jp-muted">{field.description}</p> : null}
                </div>
              </div>
            );
          }

          if (field.type === "textarea") {
            return (
              <div key={field.key} className="sm:col-span-2">
                <Label htmlFor={id}>{field.label}</Label>
                {field.description ? <p className="text-xs text-jp-muted">{field.description}</p> : null}
                <textarea
                  id={id}
                  className="mt-1 w-full rounded-xl border border-jp-border px-3 py-2 text-sm"
                  rows={2}
                  value={typeof value === "string" ? value : ""}
                  onChange={(e) => updateField(field.key, e.target.value)}
                  maxLength={field.maxLength}
                />
              </div>
            );
          }

          if (field.type === "select") {
            return (
              <div key={field.key}>
                <Label htmlFor={id}>{field.label}</Label>
                {field.description ? <p className="text-xs text-jp-muted">{field.description}</p> : null}
                <Select
                  id={id}
                  className="mt-1"
                  value={typeof value === "string" ? value : ""}
                  onChange={(e) => updateField(field.key, e.target.value)}
                >
                  {(field.options ?? []).map((option) => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </Select>
              </div>
            );
          }

          return (
            <div key={field.key}>
              <Label htmlFor={id}>{field.label}</Label>
              {field.description ? <p className="text-xs text-jp-muted">{field.description}</p> : null}
              <input
                id={id}
                type={field.type === "number" ? "number" : field.type}
                className="mt-1 w-full min-h-11 rounded-xl border border-jp-border px-3 text-sm"
                value={typeof value === "number" || typeof value === "string" ? value : ""}
                onChange={(e) =>
                  updateField(field.key, field.type === "number" ? Number(e.target.value) : e.target.value)
                }
                maxLength={field.maxLength}
                min={field.min}
                max={field.max}
              />
            </div>
          );
        })}
      </div>

      <div className="mt-4 flex flex-wrap gap-2">
        <Button type="button" size="sm" onClick={applyPreview}>
          Apply to preview
        </Button>
        <Button type="button" size="sm" variant="ghost" onClick={handleReset} disabled={!dirty}>
          Reset preview
        </Button>
      </div>
    </section>
  );
}
