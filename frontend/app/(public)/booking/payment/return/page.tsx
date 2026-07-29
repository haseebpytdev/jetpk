import { redirect } from "next/navigation";

type PageProps = {
  searchParams: Promise<Record<string, string | string[] | undefined>>;
};

export default async function Page({ searchParams }: PageProps) {
  const raw = await searchParams;
  const reference = Array.isArray(raw.reference) ? raw.reference[0] : raw.reference;
  const query = new URLSearchParams();
  if (reference) query.set("reference", reference);
  const suffix = query.toString() ? `?${query.toString()}` : "";
  redirect(`/booking/payment/status${suffix}`);
}
