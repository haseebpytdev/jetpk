/** Empty-state body copy — never claim synthetic preview data on live operational builds. */
export function emptyListDescription(isLive: boolean, resourceLabel = "results"): string {
  if (isLive) {
    return `Try clearing filters or broadening your search. ${resourceLabel} come from the live JetPakistan backend.`;
  }
  return "Try clearing filters or broadening your search. Preview mode may show fixture data.";
}
