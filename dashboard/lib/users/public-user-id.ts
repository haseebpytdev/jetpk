/**
 * Dashboard directory IDs are public (`JP-USR-0007`); Admin UserManagement
 * routes bind numeric Laravel user IDs.
 */
export function laravelUserIdFromPublicId(id: string | number): string {
  const raw = String(id).trim();
  const match = raw.match(/^JP-USR-0*(\d+)$/i);
  if (match) {
    return match[1];
  }
  if (/^\d+$/.test(raw)) {
    return raw;
  }
  return raw;
}

export function publicUserIdFromLaravelId(id: string | number): string {
  const numeric = laravelUserIdFromPublicId(id);
  if (!/^\d+$/.test(numeric)) {
    return String(id);
  }
  return `JP-USR-${numeric.padStart(4, "0")}`;
}
