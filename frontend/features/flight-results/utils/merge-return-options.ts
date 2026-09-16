/**
 * Progressive merge for split-return option polls.
 * Active: append/update by combo_id. Terminal: canonical replacement.
 */
export function mergeProgressiveReturnOptions(
  current: Array<Record<string, unknown>>,
  incoming: Array<Record<string, unknown>>,
  pipelineStatus: string,
): Array<Record<string, unknown>> {
  const normalized = pipelineStatus.toLowerCase();
  const terminal =
    normalized === "ready" ||
    normalized === "empty" ||
    normalized === "failed" ||
    normalized === "expired" ||
    normalized === "error";

  if (terminal || current.length === 0) {
    return incoming;
  }

  const byId = new Map(current.map((row) => [String(row.combo_id ?? ""), row]));
  for (const row of incoming) {
    byId.set(String(row.combo_id ?? ""), row);
  }
  return Array.from(byId.values());
}
