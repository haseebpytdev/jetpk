/**
 * Secret-safe sanitization for responsive certification logs and evidence.
 */
const REDACT_PATTERNS = [
  [/Cookie:\s*[^\s;]+(?:;\s*[^\s;]+)*/gi, "Cookie: [REDACTED]"],
  [/Set-Cookie:\s*[^\r\n]+/gi, "Set-Cookie: [REDACTED]"],
  [/Authorization:\s*[^\s\r\n]+/gi, "Authorization: [REDACTED]"],
  [/Bearer\s+[A-Za-z0-9._~+/=-]+/gi, "Bearer [REDACTED]"],
  [/XSRF-TOKEN[=:]\s*[^\s;,\r\n]+/gi, "XSRF-TOKEN=[REDACTED]"],
  [/jetpakistan-session[=:]\s*[^\s;,\r\n]+/gi, "jetpakistan-session=[REDACTED]"],
  [/password[=:]\s*[^\s&"']+/gi, "password=[REDACTED]"],
  [/[?&](token|secret|api_key|apikey|password)=[^&\s"']+/gi, "?$1=[REDACTED]"],
  [/"cookie"\s*:\s*"[^"]+"/gi, '"cookie": "[REDACTED]"'],
];

export function sanitizeCertificationError(input) {
  let text = typeof input === "string" ? input : String(input?.message ?? input ?? "");
  if (input && typeof input === "object" && input.stack) {
    text = `${text}\n${input.stack}`;
  }
  for (const [re, repl] of REDACT_PATTERNS) {
    text = text.replace(re, repl);
  }
  if (/Call log:|api\.request\.headers/i.test(text)) {
    text = text.replace(/api\.request\.headers[\s\S]*?(?=\n\n|\n\s*at )/gi, "api.request.headers [REDACTED]\n");
  }
  return text.slice(0, 8000);
}

export function sanitizeForJson(value) {
  if (value == null) return value;
  if (typeof value === "string") return sanitizeCertificationError(value);
  if (Array.isArray(value)) return value.map(sanitizeForJson);
  if (typeof value === "object") {
    const out = {};
    for (const [k, v] of Object.entries(value)) {
      if (/cookie|authorization|token|password|secret/i.test(k)) {
        out[k] = "[REDACTED]";
      } else {
        out[k] = sanitizeForJson(v);
      }
    }
    return out;
  }
  return value;
}
