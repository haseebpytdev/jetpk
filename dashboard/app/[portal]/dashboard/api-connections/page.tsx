import { redirect } from "next/navigation";

export const metadata = { title: "Integrations — JetPakistan Dashboard" };

/**
 * Legacy bookmark: /admin/dashboard/api-connections
 * Authoritative surface is Integrations Hub.
 */
export default async function ApiConnectionsLegacyRedirectPage({
  params,
  searchParams,
}: {
  params: Promise<{ portal: string }>;
  searchParams: Promise<Record<string, string | string[] | undefined>>;
}) {
  const { portal } = await params;
  const query = await searchParams;
  const qs = new URLSearchParams();
  for (const [key, value] of Object.entries(query)) {
    if (Array.isArray(value)) {
      value.forEach((item) => qs.append(key, item));
    } else if (value !== undefined) {
      qs.set(key, value);
    }
  }
  // Map legacy provider query onto Integrations deep-link when present.
  if (!qs.has("provider") && typeof query.provider === "string") {
    qs.set("provider", query.provider);
  }
  const suffix = qs.toString() ? `?${qs.toString()}` : "";

  redirect(`/${portal}/dashboard/integrations${suffix}`);
}
