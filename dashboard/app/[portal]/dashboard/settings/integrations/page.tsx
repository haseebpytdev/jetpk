import { redirect } from "next/navigation";

export const metadata = { title: "API Connections — JetPakistan Dashboard" };

export default async function SettingsIntegrationsRedirectPage({
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
  const suffix = qs.toString() ? `?${qs.toString()}` : "";

  redirect(`/${portal}/dashboard/api-connections${suffix}`);
}
