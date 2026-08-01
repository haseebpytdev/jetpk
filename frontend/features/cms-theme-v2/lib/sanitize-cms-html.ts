const ALLOWED_TAGS = new Set([
  "p", "h2", "h3", "h4", "h5", "h6", "strong", "em", "b", "i", "u", "s", "sup", "sub",
  "br", "hr", "a", "ul", "ol", "li", "blockquote", "figure", "figcaption", "img",
  "table", "thead", "tbody", "tfoot", "tr", "th", "td", "caption", "code", "pre",
  "span", "div", "dl", "dt", "dd", "abbr",
]);

const GLOBAL_ATTRS = new Set(["lang", "dir"]);
const TAG_ATTRS: Record<string, Set<string>> = {
  a: new Set(["href", "title"]),
  img: new Set(["src", "alt", "width", "height", "loading"]),
  th: new Set(["colspan", "rowspan", "scope"]),
  td: new Set(["colspan", "rowspan", "scope"]),
  abbr: new Set(["title"]),
  h2: new Set(["id"]),
  h3: new Set(["id"]),
  h4: new Set(["id"]),
  h5: new Set(["id"]),
  h6: new Set(["id"]),
};

const FORBIDDEN_TAGS = /<\/?(script|style|link|meta|base|object|embed|applet|form|input|button|select|textarea|iframe)\b[^>]*>/gi;
const EVENT_HANDLERS = /\s+on[a-z]+\s*=\s*("[^"]*"|'[^']*'|[^\s>]+)/gi;
const STYLE_ATTR = /\s+style\s*=\s*("[^"]*"|'[^']*'|[^\s>]+)/gi;
const CLASS_ATTR = /\s+class\s*=\s*("[^"]*"|'[^']*'|[^\s>]+)/gi;

function isAllowedHref(value: string): boolean {
  const v = value.trim().toLowerCase();
  if (!v) return false;
  if (v.startsWith("javascript:") || v.startsWith("data:") || v.startsWith("vbscript:")) {
    return false;
  }
  if (v.startsWith("/") && !v.startsWith("//")) return true;
  if (v.startsWith("mailto:") || v.startsWith("tel:")) return true;
  return v.startsWith("http://") || v.startsWith("https://");
}

function sanitizeOpenTag(tag: string): string {
  const match = tag.match(/^<([a-z0-9]+)\b/i);
  if (!match) return "";
  let tagName = match[1].toLowerCase();
  if (tagName === "h1") {
    tagName = "h2";
  }
  if (!ALLOWED_TAGS.has(tagName)) {
    return "";
  }

  const allowedForTag = TAG_ATTRS[tagName] ?? new Set<string>();
  const attrPattern = /([a-z][a-z0-9-]*)\s*=\s*("([^"]*)"|'([^']*)'|([^\s>]+))/gi;
  const attrs: string[] = [];
  let attrMatch: RegExpExecArray | null;
  const tagContent = tag;

  while ((attrMatch = attrPattern.exec(tagContent)) !== null) {
    const name = attrMatch[1].toLowerCase();
    const value = attrMatch[3] ?? attrMatch[4] ?? attrMatch[5] ?? "";
    if (name.startsWith("on")) continue;
    if (name === "style" || name === "class") continue;
    if (!GLOBAL_ATTRS.has(name) && !allowedForTag.has(name)) continue;
    if ((name === "href" || name === "src") && !isAllowedHref(value)) continue;
    if (name === "href" && (value.startsWith("http://") || value.startsWith("https://"))) {
      attrs.push(`href="${value}"`);
      attrs.push('rel="noopener noreferrer"');
      continue;
    }
    attrs.push(`${name}="${value.replace(/"/g, "&quot;")}"`);
  }

  if (tag.endsWith("/>") || tag.endsWith("/ >")) {
    return `<${tagName}${attrs.length ? " " + attrs.join(" ") : ""} />`;
  }
  return `<${tagName}${attrs.length ? " " + attrs.join(" ") : ""}>`;
}

/**
 * Custom allowlist HTML sanitizer for CMS rich text.
 * Strips scripts, handlers, styles, classes, forms, and unsafe URLs.
 */
export function sanitizeCmsHtml(html: string): string {
  if (!html || !html.trim()) {
    return "";
  }

  let result = html
    .replace(FORBIDDEN_TAGS, "")
    .replace(EVENT_HANDLERS, "")
    .replace(STYLE_ATTR, "")
    .replace(CLASS_ATTR, "");

  result = result.replace(/<\/?[a-z][a-z0-9]*\b[^>]*>/gi, (tag) => {
    if (tag.startsWith("</")) {
      const name = tag.replace(/<\/?|\/?>/g, "").toLowerCase();
      if (name === "h1") return "</h2>";
      return ALLOWED_TAGS.has(name) ? `</${name}>` : "";
    }
    return sanitizeOpenTag(tag);
  });

  return result.trim();
}

export function containsUnsafeCmsHtml(html: string): boolean {
  if (!html) return false;
  return (
    /<script|javascript:|data:|vbscript:|\son\w+\s*=/i.test(html) ||
    /<(style|iframe|form|input|button)\b/i.test(html)
  );
}
