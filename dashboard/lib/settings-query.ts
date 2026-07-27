import type { SettingsSection } from "@/types/access-control";
import type { SettingsQuery } from "@/types/settings-module";

function first(value: string | string[] | undefined): string {
  if (Array.isArray(value)) return value[0] ?? "";
  return value ?? "";
}

const SECTIONS: (SettingsSection | "overview")[] = ["overview", "general", "security", "notifications", "integrations"];

export function parseSettingsQuery(
  searchParams: Record<string, string | string[] | undefined>,
): SettingsQuery {
  const sectionRaw = first(searchParams.selectedSection) || first(searchParams.section);
  const validationRaw = first(searchParams.validationState);

  return {
    selectedSection: (SECTIONS as readonly string[]).includes(sectionRaw)
      ? (sectionRaw as SettingsSection | "overview")
      : "overview",
    validationState:
      validationRaw === "valid" || validationRaw === "warning" || validationRaw === "blocked"
        ? validationRaw
        : "all",
    state: first(searchParams.state),
    preview: first(searchParams.preview) === "1",
    tab: first(searchParams.tab),
    previewError: first(searchParams.previewError) === "1",
    previewLoading: first(searchParams.previewLoading) === "1",
    previewEmpty: first(searchParams.previewEmpty) === "1",
  };
}

export function settingsQueryToSearchParams(query: SettingsQuery, overrides?: Partial<SettingsQuery>): string {
  const merged = { ...query, ...overrides };
  const params = new URLSearchParams();

  if (merged.selectedSection !== "overview") params.set("selectedSection", merged.selectedSection);
  if (merged.validationState !== "all") params.set("validationState", merged.validationState);
  if (merged.state) params.set("state", merged.state);
  if (merged.preview) params.set("preview", "1");
  if (merged.tab) params.set("tab", merged.tab);
  if (merged.previewError) params.set("previewError", "1");
  if (merged.previewLoading) params.set("previewLoading", "1");
  if (merged.previewEmpty) params.set("previewEmpty", "1");

  const s = params.toString();
  return s ? `?${s}` : "";
}

export function defaultSettingsQuery(): SettingsQuery {
  return parseSettingsQuery({});
}
