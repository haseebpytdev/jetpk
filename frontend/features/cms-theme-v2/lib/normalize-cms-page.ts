import type { CmsBlock, CmsPagePayload } from "./block-types";
import { resolvePageTemplate } from "./page-template-registry";

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}

function asString(value: unknown): string | undefined {
  return typeof value === "string" && value.trim() ? value.trim() : undefined;
}

function normalizeBlock(raw: unknown): CmsBlock | null {
  if (!isRecord(raw) || typeof raw.type !== "string") {
    return null;
  }

  switch (raw.type) {
    case "hero":
      if (typeof raw.heading !== "string") return null;
      return {
        type: "hero",
        eyebrow: asString(raw.eyebrow),
        heading: raw.heading,
        body: asString(raw.body),
        image: isRecord(raw.image) && typeof raw.image.src === "string" && typeof raw.image.alt === "string"
          ? { src: raw.image.src, alt: raw.image.alt }
          : undefined,
        actions: Array.isArray(raw.actions)
          ? raw.actions
              .filter((a): a is { label: string; href: string } =>
                isRecord(a) && typeof a.label === "string" && typeof a.href === "string",
              )
          : undefined,
      };
    case "richText":
      if (typeof raw.html !== "string") return null;
      return { type: "richText", html: raw.html };
    case "image":
      if (!isRecord(raw.image) || typeof raw.image.src !== "string" || typeof raw.image.alt !== "string") {
        return null;
      }
      return {
        type: "image",
        image: { src: raw.image.src, alt: raw.image.alt },
        caption: asString(raw.caption),
        width: raw.width === "wide" || raw.width === "full" ? raw.width : "container",
      };
    case "cardGrid":
      if (!Array.isArray(raw.items)) return null;
      return {
        type: "cardGrid",
        heading: asString(raw.heading),
        body: asString(raw.body),
        columns: raw.columns === 2 || raw.columns === 3 || raw.columns === 4 ? raw.columns : 3,
        items: raw.items
          .filter((item): item is { heading: string; body?: string; href?: string; icon?: string } =>
            isRecord(item) && typeof item.heading === "string",
          )
          .map((item) => ({
            heading: item.heading,
            body: asString(item.body),
            href: asString(item.href),
            icon: asString(item.icon),
          })),
      };
    case "stats":
      if (!Array.isArray(raw.items)) return null;
      return {
        type: "stats",
        heading: asString(raw.heading),
        items: raw.items
          .filter((item): item is { value: string; label: string } =>
            isRecord(item) && typeof item.value === "string" && typeof item.label === "string",
          ),
      };
    case "timeline":
      if (!Array.isArray(raw.items)) return null;
      return {
        type: "timeline",
        heading: asString(raw.heading),
        items: raw.items
          .filter((item): item is { marker: string; heading: string; body?: string } =>
            isRecord(item) && typeof item.marker === "string" && typeof item.heading === "string",
          ),
      };
    case "faq":
      if (!Array.isArray(raw.items)) return null;
      return {
        type: "faq",
        heading: asString(raw.heading),
        items: raw.items
          .filter((item): item is { question: string; answer: string } =>
            isRecord(item) && typeof item.question === "string" && typeof item.answer === "string",
          ),
      };
    case "callout":
      if (typeof raw.body !== "string") return null;
      return {
        type: "callout",
        tone: raw.tone === "success" || raw.tone === "warning" || raw.tone === "danger" ? raw.tone : "info",
        heading: asString(raw.heading),
        body: raw.body,
        action:
          isRecord(raw.action) && typeof raw.action.label === "string" && typeof raw.action.href === "string"
            ? { label: raw.action.label, href: raw.action.href }
            : undefined,
      };
    case "gallery":
      if (!Array.isArray(raw.items)) return null;
      return {
        type: "gallery",
        heading: asString(raw.heading),
        columns: raw.columns === 2 || raw.columns === 3 || raw.columns === 4 ? raw.columns : 3,
        items: raw.items
          .filter((item): item is { src: string; alt: string } =>
            isRecord(item) && typeof item.src === "string" && typeof item.alt === "string",
          ),
      };
    case "section":
      return {
        type: "section",
        heading: asString(raw.heading),
        body: asString(raw.body),
        blocks: Array.isArray(raw.blocks)
          ? raw.blocks.map(normalizeBlock).filter((b): b is CmsBlock => b !== null)
          : undefined,
      };
    default:
      return null;
  }
}

export function normalizeCmsPage(input: unknown): CmsPagePayload {
  const record = isRecord(input) ? input : {};
  const blocks = Array.isArray(record.blocks)
    ? record.blocks.map(normalizeBlock).filter((b): b is CmsBlock => b !== null)
    : [];

  const pageKey = asString(record.pageKey);
  const slug = asString(record.slug);
  const routeFamily = asString(record.routeFamily);
  const templateInput = asString(record.template);

  return {
    title: asString(record.title),
    pageKey,
    slug,
    routeFamily,
    template: resolvePageTemplate({ template: templateInput, pageKey, slug, routeFamily }),
    blocks,
  };
}
